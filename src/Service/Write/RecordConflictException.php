<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Ein neuer Datensatz ließ sich wegen gleichzeitiger Anlagen mit demselben Namen
 * auch nach mehreren Versuchen nicht mit eindeutigem Alias speichern. Der
 * Controller antwortet mit 409 im einheitlichen JSON-Format (E31); das Mitglied
 * kann erneut absenden.
 */
#[Exclude]
final class RecordConflictException extends \RuntimeException
{
    public const MESSAGE = 'Der Eintrag konnte gerade nicht gespeichert werden. Bitte senden Sie das Formular erneut ab.';

    public function __construct(\Throwable|null $previous = null)
    {
        parent::__construct(self::MESSAGE, 0, $previous);
    }
}
