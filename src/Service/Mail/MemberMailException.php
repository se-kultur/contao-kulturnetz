<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Mail;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Die Freischaltungsmail kann nicht gesendet werden (kein Mitglied oder keine
 * E-Mail-Adresse). Die Meldung ist für die Redaktion bestimmt und wird im Backend
 * am Feld "E-Mail senden?" angezeigt.
 */
#[Exclude]
final class MemberMailException extends \RuntimeException
{
}
