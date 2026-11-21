<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\DataContainer\Label;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Mitgliederdaten (Name, E-Mail) für die Backend-Listen der KulturNetz-Tabellen.
 *
 * Der label_callback läuft je Listenzeile. Bisher fragte er je Zeile das
 * Mitglied ab (SELECT * FROM tl_member). Jetzt lädt der erste Aufruf je Tabelle
 * alle Mitglieder, die in dieser Tabelle vorkommen, mit einer einzigen Abfrage.
 */
final class ListMemberDirectory implements ResetInterface
{
    private const TABLES = ['tl_artists', 'tl_hosts', 'tl_locations', 'tl_events', 'tl_sekevents', 'tl_postings'];

    /**
     * @var array<string, array<int, array{id: int, firstname: string, lastname: string, email: string}>>
     */
    private array $members = [];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{id: int, firstname: string, lastname: string, email: string}|null
     */
    public function find(string $table, int $memberId): array|null
    {
        if (!\in_array($table, self::TABLES, true)) {
            throw new \InvalidArgumentException(\sprintf('Die Tabelle "%s" hat keine Mitgliederzuordnung.', $table));
        }

        if (!isset($this->members[$table])) {
            $this->members[$table] = [];

            $rows = $this->connection->fetchAllAssociative(
                "SELECT id, firstname, lastname, email FROM tl_member WHERE id IN (SELECT member_id FROM $table)",
            );

            foreach ($rows as $row) {
                $this->members[$table][(int) $row['id']] = [
                    'id' => (int) $row['id'],
                    'firstname' => (string) $row['firstname'],
                    'lastname' => (string) $row['lastname'],
                    'email' => (string) $row['email'],
                ];
            }
        }

        return $this->members[$table][$memberId] ?? null;
    }

    public function reset(): void
    {
        $this->members = [];
    }
}
