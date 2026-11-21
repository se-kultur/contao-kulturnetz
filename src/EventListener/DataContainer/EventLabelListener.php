<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use SeKultur\ContaoKulturnetzBundle\EventListener\DataContainer\Label\RecordLabelBuilder;

/**
 * Spalten der Backend-Listen "Veranstaltungen" und "Events" (SE-KulturTage),
 * bisher addLabelData() in tl_events.php und tl_sekevents.php.
 *
 * Wie bei den Profilen, zusätzlich ein Schloss-Symbol hinter dem Namen, wenn
 * die Bearbeitung im Frontend gesperrt ist (readonly). Die Frontend-Vorschau
 * öffnet die Seite "Veranstaltungen" des Mitgliederbereichs (bisher fest
 * Seite 40, jetzt per Alias aus der Bundle-Konfiguration). Welche Spalten
 * erscheinen, hängt von der Sortierung ab (EventPanelListener).
 */
#[AsCallback(table: 'tl_events', target: 'list.label.label')]
#[AsCallback(table: 'tl_sekevents', target: 'list.label.label')]
final class EventLabelListener
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
                'name' => $this->labels->frontendLink($type, (string) ($row['alias'] ?? ''), (string) ($row['name'] ?? ''))
                    .('1' === (string) ($row['readonly'] ?? '') ? $this->labels->readonlyIcon() : ''),
                'member_id' => $this->labels->memberWithPreview($table, $memberId, 'own_events'),
                'email' => $this->labels->memberEmail($table, $memberId),
                'created', 'tstamp' => $this->labels->date((int) ($row[$field] ?? 0)),
                default => $args[$index] ?? '',
            };
        }

        return $args;
    }
}
