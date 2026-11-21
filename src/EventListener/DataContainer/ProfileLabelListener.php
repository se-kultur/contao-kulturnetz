<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use SeKultur\ContaoKulturnetzBundle\EventListener\DataContainer\Label\RecordLabelBuilder;

/**
 * Spalten der Backend-Listen "Künstler", "Veranstalter" und "Locations"
 * (bisher addLabelData() in tl_artists.php, tl_hosts.php, tl_locations.php).
 *
 * Name als Link auf die Detailseite im Frontend, Mitglied mit Vorschau-Link,
 * E-Mail des Mitglieds, Erstellt am und Bearbeitet am als "TT.MM.JJ - HH:MM".
 * Die übrigen Spalten bleiben, wie Contao sie formatiert.
 */
#[AsCallback(table: 'tl_artists', target: 'list.label.label')]
#[AsCallback(table: 'tl_hosts', target: 'list.label.label')]
#[AsCallback(table: 'tl_locations', target: 'list.label.label')]
final class ProfileLabelListener
{
    public function __construct(private readonly RecordLabelBuilder $labels)
    {
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string>   $args
     *
     * @return array<int, string>
     */
    public function __invoke(array $row, string $label, DataContainer $dc, array $args): array
    {
        $table = $dc->table;
        $type = substr($table, 3);
        $memberId = (int) ($row['member_id'] ?? 0);

        foreach ($GLOBALS['TL_DCA'][$table]['list']['label']['fields'] ?? [] as $index => $field) {
            $args[$index] = match ($field) {
                'name' => $this->labels->frontendLink($type, (string) ($row['alias'] ?? ''), (string) ($row['name'] ?? '')),
                'member_id' => $this->labels->memberWithPreview($table, $memberId),
                'email' => $this->labels->memberEmail($table, $memberId),
                'created', 'tstamp' => $this->labels->date((int) ($row[$field] ?? 0)),
                default => $args[$index] ?? '',
            };
        }

        return $args;
    }
}
