<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Security;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;

/**
 * Besitzprüfungen der Endpunkte: Gehört ein Datensatz, ein Beitrag oder eine
 * Datei dem angemeldeten Mitglied?
 *
 * Grundlage ist immer die Datenbank, nie ein Wert aus dem Request (die bisherigen
 * Felder member_id, security und alias der Formulare werden nicht ausgewertet).
 * Tabellennamen kommen nur über die feste Typ-Liste zustande (artists, hosts,
 * locations, events, sekevents; als Typschlüssel oder Tabellenname mit "tl_").
 */
final class OwnershipChecker
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Typschlüssel zu einem Typschlüssel oder Tabellennamen ("artists" oder
     * "tl_artists"), null für alles außerhalb der Typ-Liste.
     *
     * @param list<string>|null $allowed engere Auswahl, z. B. nur Profiltypen
     */
    public function resolveType(string $typeOrTable, array|null $allowed = null): string|null
    {
        $type = str_starts_with($typeOrTable, 'tl_') ? substr($typeOrTable, 3) : $typeOrTable;
        $allowed ??= KulturnetzConfig::TYPES;

        return \in_array($type, $allowed, true) && \in_array($type, KulturnetzConfig::TYPES, true) ? $type : null;
    }

    /**
     * True, wenn der Datensatz existiert und dem Mitglied gehört (member_id).
     */
    public function ownsRecord(string $type, int $recordId, int $memberId): bool
    {
        $table = $this->table($type);

        if ($recordId < 1 || $memberId < 1) {
            return false;
        }

        $owner = $this->connection->fetchOne(
            "SELECT member_id FROM $table WHERE id = ?",
            [$recordId],
            [ParameterType::INTEGER],
        );

        return false !== $owner && (int) $owner === $memberId;
    }

    /**
     * True, wenn die Veranstaltung schreibgeschützt ist (Feld "readonly", nach der
     * Freischaltung der SE-KulturTage-Events durch die Redaktion). Profile haben
     * keinen Schreibschutz.
     */
    public function isReadonly(string $type, int $recordId): bool
    {
        $table = $this->table($type);

        if (!\in_array($type, ['events', 'sekevents'], true)) {
            return false;
        }

        return '1' === (string) $this->connection->fetchOne(
            "SELECT readonly FROM $table WHERE id = ?",
            [$recordId],
            [ParameterType::INTEGER],
        );
    }

    /**
     * True, wenn der Beitrag existiert und dem Mitglied gehört.
     */
    public function ownsPosting(int $postingId, int $memberId): bool
    {
        if ($postingId < 1 || $memberId < 1) {
            return false;
        }

        $owner = $this->connection->fetchOne(
            'SELECT member_id FROM tl_postings WHERE id = ?',
            [$postingId],
            [ParameterType::INTEGER],
        );

        return false !== $owner && (int) $owner === $memberId;
    }

    /**
     * Pfad des Mitgliedsordners (tl_member.homeDir) relativ zum Projektverzeichnis,
     * null ohne Ordner.
     */
    public function getHomeDirPath(int $memberId): string|null
    {
        if ($memberId < 1) {
            return null;
        }

        $homeDir = $this->connection->fetchOne(
            'SELECT homeDir FROM tl_member WHERE id = ?',
            [$memberId],
            [ParameterType::INTEGER],
        );

        if (!\is_string($homeDir) || 16 !== \strlen($homeDir)) {
            return null;
        }

        $path = $this->connection->fetchOne(
            "SELECT path FROM tl_files WHERE uuid = ? AND type = 'folder'",
            [$homeDir],
            [ParameterType::BINARY],
        );

        return \is_string($path) && '' !== $path ? $path : null;
    }

    /**
     * True, wenn die Datei (UUID binär oder als Text) in der Dateiverwaltung
     * existiert und im Mitgliedsordner des Mitglieds oder darunter liegt.
     */
    public function isFileInHomeDir(string $fileUuid, int $memberId): bool
    {
        $homeDir = $this->getHomeDirPath($memberId);
        $binary = $this->toBinaryUuid($fileUuid);

        if (null === $homeDir || null === $binary) {
            return false;
        }

        $path = $this->connection->fetchOne(
            "SELECT path FROM tl_files WHERE uuid = ? AND type = 'file'",
            [$binary],
            [ParameterType::BINARY],
        );

        return \is_string($path) && str_starts_with($path, rtrim($homeDir, '/').'/');
    }

    private function table(string $type): string
    {
        if (!\in_array($type, KulturnetzConfig::TYPES, true)) {
            throw new \InvalidArgumentException(\sprintf('Unbekannter KulturNetz-Typ "%s".', $type));
        }

        return 'tl_'.$type;
    }

    /**
     * UUID binär (16 Byte) oder als Text in binärer Form. Contao\Validator::isUuid()
     * akzeptiert nur Version 1; die früheren Upload-Skripte haben Version-4-UUIDs
     * geschrieben, deshalb hier jede Version.
     */
    private function toBinaryUuid(string $uuid): string|null
    {
        if (16 === \strlen($uuid)) {
            return $uuid;
        }

        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
            return null;
        }

        return hex2bin(str_replace('-', '', $uuid)) ?: null;
    }
}
