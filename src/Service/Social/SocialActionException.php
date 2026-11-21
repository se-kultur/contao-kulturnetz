<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Social;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\Response;

/**
 * Abgelehnte Aktion beim Folgen oder bei Beiträgen.
 *
 * Die Meldung ist der Text, den das Mitglied sieht (wortgleich zu den früheren
 * Skripten posting.php und follow.php, soweit es dort einen Text gab). Der Status
 * entspricht der Fehlerantwort der Endpunkte (Entscheidung E31): 403 kein Besitz,
 * 404 nicht gefunden, 422 ungültige Eingabe, 500 Datenbankfehler.
 */
#[Exclude]
final class SocialActionException extends \RuntimeException
{
    public const MESSAGE_ACCESS_DENIED = 'Zugriff verweigert';

    public const MESSAGE_DATABASE_ERROR = 'Es ist ein Datenbank-Fehler aufgetreten.';

    public const MESSAGE_CONTENT_TOO_SHORT = 'Bitte füllen Sie das Inhaltsfeld aus. Verwenden Sie dafür mindestens 10 Zeichen.';

    /**
     * Unbekannte Aktion (bisher posting.php, Meldung #100).
     */
    public const MESSAGE_UNKNOWN_ACTION = 'Zugriff verweigert #100';

    /**
     * Beitrag nicht gefunden (bisher Meldung #101).
     */
    public const MESSAGE_POSTING_NOT_FOUND = 'Zugriff verweigert #101';

    /**
     * Löschen eines fremden Beitrags (bisher Meldung #102).
     */
    public const MESSAGE_DELETE_DENIED = 'Zugriff verweigert #102';

    /**
     * Bearbeiten eines fremden Beitrags (bisher Meldung #103).
     */
    public const MESSAGE_EDIT_DENIED = 'Zugriff verweigert #103';

    /**
     * Inhaltsfeld fehlt (bisher Meldung #104).
     */
    public const MESSAGE_CONTENT_MISSING = 'Es ist ein Fehler aufgetreten #104';

    /**
     * Folgen desselben Profils (neu, im Formular nicht auslösbar).
     */
    public const MESSAGE_SELF_FOLLOW = 'Ein Profil kann sich nicht selbst folgen.';

    private function __construct(string $message, private readonly int $status, \Throwable|null $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function forbidden(string $message = self::MESSAGE_ACCESS_DENIED): self
    {
        return new self($message, Response::HTTP_FORBIDDEN);
    }

    public static function notFound(string $message): self
    {
        return new self($message, Response::HTTP_NOT_FOUND);
    }

    public static function invalid(string $message): self
    {
        return new self($message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public static function databaseError(\Throwable $previous): self
    {
        return new self(self::MESSAGE_DATABASE_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR, $previous);
    }

    /**
     * HTTP-Status der Fehlerantwort.
     */
    public function getStatus(): int
    {
        return $this->status;
    }
}
