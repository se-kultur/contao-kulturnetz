<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;

/**
 * Prüft die verknüpften Profile einer Veranstaltung (linked_artists,
 * linked_hosts, linked_locations).
 *
 * Das Formular-JS sendet die Auswahl als JSON-Liste der IDs als Zeichenketten
 * (JSON.stringify der Objektschlüssel, z. B. ["12","34"]); in diesem Format
 * bleibt sie gespeichert. Zulässig sind nur positive ganze Zahlen. Profile, die
 * es nicht mehr gibt, fallen heraus (sonst ließe sich eine Veranstaltung mit
 * einem inzwischen gelöschten Profil nicht mehr speichern). Gesperrte Profile
 * anderer Mitglieder dürfen nur verknüpft sein, wenn sie es schon waren; die
 * Suche (kulturnetz_suche_profile) bietet sie gar nicht an.
 */
final class LinkedProfileFilter
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Gefilterte ID-Liste als JSON wie im Formular (["12","34"]).
     *
     * @param list<int> $currentIds bisher gespeicherte Verknüpfungen
     *
     * @throws \UnexpectedValueException bei ungültiger Liste oder unzulässigem Profil
     */
    public function filter(string $profileType, string $value, int $memberId, array $currentIds = []): string
    {
        if (!\in_array($profileType, KulturnetzConfig::PROFILE_TYPES, true)) {
            throw new \InvalidArgumentException(\sprintf('Unbekannter Profiltyp "%s".', $profileType));
        }

        $ids = self::parse($value);

        if (null === $ids) {
            throw new \UnexpectedValueException('Ungültige Liste');
        }

        if ([] === $ids) {
            return '[]';
        }

        $rows = $this->connection->fetchAllAssociativeIndexed(
            "SELECT id, disable, member_id FROM tl_$profileType WHERE id IN (?)",
            [$ids],
            [ArrayParameterType::INTEGER],
        );

        $result = [];

        foreach ($ids as $id) {
            $row = $rows[$id] ?? null;

            if (null === $row) {
                continue;
            }

            $foreignDisabled = '1' === (string) $row['disable'] && (int) $row['member_id'] !== $memberId;

            if ($foreignDisabled && !\in_array($id, $currentIds, true)) {
                throw new \UnexpectedValueException('Unzulässiges Profil');
            }

            $result[] = (string) $id;
        }

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    /**
     * IDs aus einem gespeicherten oder gesendeten Wert; null bei ungültigem Format.
     * Leere Werte ergeben eine leere Liste. Doppelte IDs entfallen, die Reihenfolge
     * bleibt.
     *
     * @return list<int>|null
     */
    public static function parse(string $value): array|null
    {
        if ('' === trim($value)) {
            return [];
        }

        try {
            $data = json_decode($value, true, 2, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($data) || !array_is_list($data)) {
            return null;
        }

        $ids = [];

        foreach ($data as $item) {
            if (\is_int($item)) {
                $id = $item;
            } elseif (\is_string($item) && 1 === preg_match('/^[1-9]\d{0,9}$/', $item)) {
                $id = (int) $item;
            } else {
                return null;
            }

            if ($id < 1 || $id > 4294967295) {
                return null;
            }

            $ids[$id] = $id;
        }

        return array_values($ids);
    }

    /**
     * Gespeicherte Verknüpfungen eines Datensatzes (ungültige Altwerte als leere Liste).
     *
     * @return list<int>
     */
    public static function parseStored(mixed $value): array
    {
        return \is_string($value) ? self::parse($value) ?? [] : [];
    }
}
