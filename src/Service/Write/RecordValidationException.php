<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Ungültige Formularwerte beim Anlegen oder Bearbeiten. Der Controller antwortet
 * mit 422 und den Feldfehlern (Formularfeldname => Meldung).
 */
#[Exclude]
final class RecordValidationException extends \RuntimeException
{
    public const MESSAGE = 'Bitte prüfen Sie Ihre Angaben.';

    /**
     * @param array<string, string> $fieldErrors
     */
    public function __construct(private readonly array $fieldErrors)
    {
        parent::__construct(self::MESSAGE);
    }

    /**
     * @return array<string, string>
     */
    public function getFieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
