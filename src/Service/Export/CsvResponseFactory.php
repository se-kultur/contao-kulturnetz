<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Export;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Erzeugt die CSV-Downloads der Redaktion im Format der bisherigen Skripte
 * unter files/se-kultur.de/code: UTF-8 mit BOM, Trennzeichen Semikolon,
 * Feldbegrenzer doppeltes Anführungszeichen, Zeilenende "\n", Download als
 * Anhang mit festem Dateinamen.
 *
 * Neu: Zellen aus Nutzereingaben, die eine Tabellenkalkulation als Formel
 * ausführen würde (Beginn mit "=", "+", "-", "@", Tabulator oder Wagenrücklauf),
 * bekommen ein vorangestelltes Hochkomma (Schutz vor Formelinjektion, OWASP
 * "CSV Injection"). Reine Zahlen bleiben unverändert. Spalten mit
 * beabsichtigten Formeln (HYPERLINK im Export der SE-KulturTage) lassen sich
 * ausnehmen. Anführungszeichen in Werten werden verdoppelt (RFC 4180, ohne
 * Escape-Zeichen).
 */
final class CsvResponseFactory
{
    private const DELIMITER = ';';

    private const ENCLOSURE = '"';

    private const BOM = "\xEF\xBB\xBF";

    /**
     * @param list<string>                     $header
     * @param iterable<list<string|int|null>>   $rows
     * @param list<int>                         $formulaColumns Spalten (0-basiert), deren Inhalt eine gewollte Formel ist
     */
    public function create(string $filename, array $header, iterable $rows, array $formulaColumns = []): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($header, $rows, $formulaColumns): void {
            $handle = fopen('php://output', 'w');

            if (false === $handle) {
                throw new \RuntimeException('Die CSV-Ausgabe konnte nicht geöffnet werden.');
            }

            fwrite($handle, self::BOM);
            fputcsv($handle, array_map($this->protect(...), $header), self::DELIMITER, self::ENCLOSURE, '');

            foreach ($rows as $row) {
                $cells = [];

                foreach (array_values($row) as $index => $value) {
                    $value = null === $value ? '' : (string) $value;
                    $cells[] = \in_array($index, $formulaColumns, true) ? $value : $this->protect($value);
                }

                fputcsv($handle, $cells, self::DELIMITER, self::ENCLOSURE, '');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename));
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /**
     * Entschärft einen Zellinhalt gegen Formelinjektion.
     */
    public function protect(string $value): string
    {
        if ('' === $value || !\in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return $value;
        }

        if (preg_match('/^[+-]?\d+(?:[.,]\d+)?$/', $value)) {
            return $value;
        }

        return "'".$value;
    }
}
