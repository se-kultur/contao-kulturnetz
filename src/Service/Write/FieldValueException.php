<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Ungültiger Wert eines Formularfeldes innerhalb von RecordInputMapper.
 *
 * $formName nennt das Formularfeld, unter dem die Meldung erscheinen soll, wenn
 * es nicht das Feld der Definition ist (z. B. "pricetag[]" bei einer Preiszeile
 * ohne Titel); null heißt das Feld der Definition (FieldDefinition::formName()).
 */
#[Exclude]
final class FieldValueException extends \UnexpectedValueException
{
    public function __construct(string $message, public readonly string|null $formName = null)
    {
        parent::__construct($message);
    }
}
