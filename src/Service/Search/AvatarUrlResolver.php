<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Search;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Bildadresse der Titelbilder in den Vorschlagslisten.
 *
 * Wie bisher die Originaldatei aus tl_files bzw. das Ersatzbild (Parameter
 * kulturnetz.image.placeholder), aber als Pfad ab der Wurzel der Website
 * ("/files/..." statt "files/..."), damit die Adresse auch ohne <base>-Tag und auf
 * Unterseiten (z. B. /event-bearbeiten/<alias>.html) stimmt. Alle Bilder einer
 * Liste werden mit einer Abfrage geladen (bisher eine Abfrage je Treffer). UUIDs
 * jeder Version werden gefunden (die früheren Upload-Skripte schrieben Version 4).
 */
final class AvatarUrlResolver
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire(param: 'kulturnetz.image.placeholder')]
        private readonly string $placeholderPath,
    ) {
    }

    /**
     * Dateipfade zu binären UUIDs (16 Byte); unbekannte UUIDs fehlen im Ergebnis.
     *
     * @param list<mixed> $uuids
     *
     * @return array<string, string> binäre UUID => Pfad
     */
    public function findPaths(array $uuids): array
    {
        $uuids = array_values(array_unique(array_filter($uuids, static fn (mixed $uuid): bool => \is_string($uuid) && 16 === \strlen($uuid))));

        if ([] === $uuids) {
            return [];
        }

        $paths = [];

        foreach ($this->connection->fetchAllAssociative('SELECT uuid, path FROM tl_files WHERE uuid IN (?)', [$uuids], [ArrayParameterType::BINARY]) as $row) {
            $paths[(string) $row['uuid']] = (string) $row['path'];
        }

        return $paths;
    }

    /**
     * Adresse eines Dateipfads ab der Wurzel der Website, ohne Datei das Ersatzbild.
     *
     * @param string $basePath Basis-Pfad des Requests (Request::getBasePath(), meist leer)
     */
    public function toUrl(string|null $path, string $basePath = ''): string
    {
        $path = null === $path || '' === $path ? $this->placeholderPath : $path;

        return $basePath.'/'.implode('/', array_map(rawurlencode(...), explode('/', ltrim($path, '/'))));
    }
}
