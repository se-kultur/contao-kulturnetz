<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use SeKultur\ContaoKulturnetzBundle\EventListener\DataContainer\Label\RecordLabelBuilder;

/**
 * Spalten der Backend-Liste "- Postings" (bisher addLabelData() in tl_postings.php).
 *
 * Der Beitragstext bleibt, wie Contao ihn formatiert. Das Mitglied erscheint
 * ohne Zeilenumbruch mit Vorschau-Link auf die Seite "Veranstaltungen" des
 * Mitgliederbereichs (bisher fest Seite 40).
 */
#[AsCallback(table: 'tl_postings', target: 'list.label.label')]
final class PostingLabelListener
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
        foreach ($GLOBALS['TL_DCA']['tl_postings']['list']['label']['fields'] ?? [] as $index => $field) {
            $args[$index] = match ($field) {
                'member_id' => $this->labels->nowrap($this->labels->memberWithPreview('tl_postings', (int) ($row['member_id'] ?? 0), 'own_events')),
                'tstamp' => $this->labels->date((int) ($row['tstamp'] ?? 0)),
                default => $args[$index] ?? '',
            };
        }

        return $args;
    }
}
