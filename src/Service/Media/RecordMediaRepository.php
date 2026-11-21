<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Media;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Service\Media\Value\MediaRecord;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Titelbild (avatar) und Galerie (multiSRC) der Profile und Veranstaltungen
 * lesen und schreiben (DBAL, gebundene Parameter, Tabellen nur aus der festen
 * Typ-Liste KulturnetzConfig::TYPES).
 *
 * Galerie-Reihenfolge (Entscheidung E25): multiSRC ist die einzige Quelle; gelesen
 * wird nur multiSRC. Leere Galerie = NULL.
 *
 * Befristeter Übergang (orderSRC-Spiegel): Solange die Spalte orderSRC existiert,
 * schreibt saveGallery() denselben Wert zusätzlich nach orderSRC. Grund: Kein
 * Template und kein Skript liest orderSRC mehr; der Spiegel verhindert nur, dass
 * GalleryOrderMigration eine abweichende orderSRC-Reihenfolge bei jedem
 * contao:migrate wieder nach multiSRC kopiert (Änderungen der Mitglieder gingen
 * verloren). Er endet von selbst, sobald die Spalte beim Go-live-DROP entfällt;
 * danach werden Spiegel, GalleryOrderMirrorListener und GalleryOrderMigration
 * gemeinsam gelöscht (siehe CONTAO6-BEREITSCHAFT.md).
 */
final class RecordMediaRepository implements ResetInterface
{
    /**
     * @var array<string, bool>
     */
    private array $orderSrcColumns = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Datensatz mit Besitzer, Schreibschutz, Titelbild und Galerie; null, wenn es
     * ihn nicht gibt.
     */
    public function findRecord(string $type, int $id): MediaRecord|null
    {
        if ($id < 1) {
            return null;
        }

        $table = $this->table($type);
        $readonly = $this->hasReadonly($type) ? 'readonly' : "''";

        $row = $this->connection->fetchAssociative(
            "SELECT id, member_id, avatar, multiSRC, $readonly AS readonly FROM $table WHERE id = ?",
            [$id],
            [ParameterType::INTEGER],
        );

        return false === $row ? null : $this->createRecord($type, $row);
    }

    /**
     * Alle Datensätze (alle Typen), die die Datei als Titelbild oder in der Galerie
     * verwenden. Bis zur Bereinigung zählt auch ein Eintrag nur in orderSRC.
     *
     * @return list<MediaRecord>
     */
    public function findReferencingRecords(string $binaryUuid): array
    {
        $records = [];

        foreach (KulturnetzConfig::TYPES as $type) {
            $table = $this->table($type);
            $readonly = $this->hasReadonly($type) ? 'readonly' : "''";
            $orderSrc = $this->hasOrderSrc($type) ? 'orderSRC' : 'NULL';

            $rows = $this->connection->fetchAllAssociative(
                "SELECT id, member_id, avatar, multiSRC, $orderSrc AS orderSRC, $readonly AS readonly FROM $table"
                .' WHERE avatar = ? OR INSTR(multiSRC, ?) > 0'.('NULL' === $orderSrc ? '' : ' OR INSTR(orderSRC, ?) > 0'),
                'NULL' === $orderSrc ? [$binaryUuid, $binaryUuid] : [$binaryUuid, $binaryUuid, $binaryUuid],
                'NULL' === $orderSrc
                    ? [ParameterType::BINARY, ParameterType::BINARY]
                    : [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY],
            );

            foreach ($rows as $row) {
                $record = $this->createRecord($type, $row);
                $inOrderSrc = \in_array($binaryUuid, $this->deserializeUuids($row['orderSRC'] ?? null), true);

                // INSTR findet die Bytes auch über Elementgrenzen hinweg; nur echte
                // Treffer zählen.
                if ($record->hasAvatar($binaryUuid) || $record->hasGalleryImage($binaryUuid) || $inOrderSrc) {
                    $records[] = $record;
                }
            }
        }

        return $records;
    }

    /**
     * Speichert die Galerie in der angegebenen Reihenfolge (multiSRC, befristet
     * gespiegelt nach orderSRC) und setzt tstamp.
     *
     * @param list<string> $binaryUuids
     */
    public function saveGallery(MediaRecord $record, array $binaryUuids): void
    {
        $value = [] === $binaryUuids ? null : serialize(array_values(array_unique($binaryUuids)));
        $table = $this->table($record->type);

        $data = ['multiSRC' => $value, 'tstamp' => $this->clock->now()->getTimestamp()];
        $types = ['multiSRC' => ParameterType::BINARY, 'tstamp' => ParameterType::INTEGER];

        if ($this->hasOrderSrc($record->type)) {
            $data['orderSRC'] = $value;
            $types['orderSRC'] = ParameterType::BINARY;
        }

        $this->connection->update($table, $data, ['id' => $record->id], [...$types, 'id' => ParameterType::INTEGER]);
    }

    /**
     * Entfernt die Datei aus der Galerie des Datensatzes, falls sie dort (oder
     * befristet nur in orderSRC) steht.
     */
    public function removeFromGallery(MediaRecord $record, string $binaryUuid): void
    {
        if (!$record->hasGalleryImage($binaryUuid) && !$this->isInLegacyOrder($record, $binaryUuid)) {
            return;
        }

        $this->saveGallery($record, array_values(array_diff($record->gallery, [$binaryUuid])));
    }

    /**
     * Leert das Titelbild, wenn es noch auf die Datei zeigt, und setzt tstamp.
     */
    public function clearAvatar(MediaRecord $record, string $binaryUuid): void
    {
        $this->connection->executeStatement(
            "UPDATE {$this->table($record->type)} SET avatar = NULL, tstamp = ? WHERE id = ? AND avatar = ?",
            [$this->clock->now()->getTimestamp(), $record->id, $binaryUuid],
            [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::BINARY],
        );
    }

    public function reset(): void
    {
        $this->orderSrcColumns = [];
    }

    /**
     * Befristet: steht die Datei in orderSRC des Datensatzes?
     */
    private function isInLegacyOrder(MediaRecord $record, string $binaryUuid): bool
    {
        if (!$this->hasOrderSrc($record->type)) {
            return false;
        }

        $value = $this->connection->fetchOne(
            "SELECT orderSRC FROM {$this->table($record->type)} WHERE id = ?",
            [$record->id],
            [ParameterType::INTEGER],
        );

        return \in_array($binaryUuid, $this->deserializeUuids($value), true);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function createRecord(string $type, array $row): MediaRecord
    {
        $avatar = $row['avatar'] ?? null;

        return new MediaRecord(
            $type,
            (int) $row['id'],
            (int) $row['member_id'],
            '1' === (string) ($row['readonly'] ?? ''),
            \is_string($avatar) && 16 === \strlen($avatar) ? $avatar : null,
            $this->deserializeUuids($row['multiSRC'] ?? null),
        );
    }

    /**
     * @return list<string>
     */
    private function deserializeUuids(mixed $value): array
    {
        if (!\is_string($value) || '' === $value) {
            return [];
        }

        $data = @unserialize($value, ['allowed_classes' => false]);
        $uuids = [];

        foreach (\is_array($data) ? $data : [] as $uuid) {
            if (\is_string($uuid) && 16 === \strlen($uuid)) {
                $uuids[] = $uuid;
            }
        }

        return array_values(array_unique($uuids));
    }

    private function table(string $type): string
    {
        if (!\in_array($type, KulturnetzConfig::TYPES, true)) {
            throw new \InvalidArgumentException(\sprintf('Unbekannter KulturNetz-Typ "%s".', $type));
        }

        return 'tl_'.$type;
    }

    private function hasReadonly(string $type): bool
    {
        return \in_array($type, ['events', 'sekevents'], true);
    }

    /**
     * Befristet: existiert die Spalte orderSRC noch (siehe Klassenkommentar)?
     */
    private function hasOrderSrc(string $type): bool
    {
        // Abfrage über information_schema (MariaDB/MySQL wie die Projekt-DB) statt über
        // die Schema-Introspektion von DBAL, deren Methoden sich in DBAL 4.x ändern.
        return $this->orderSrcColumns[$type] ??= 0 < (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'orderSRC'",
            [$this->table($type)],
        );
    }
}
