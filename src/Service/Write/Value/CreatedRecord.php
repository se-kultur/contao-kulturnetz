<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write\Value;

/**
 * Ergebnis von RecordWriter::create(): ID und Alias des neuen Datensatzes.
 */
final class CreatedRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $alias,
        public readonly bool $notified,
    ) {
    }
}
