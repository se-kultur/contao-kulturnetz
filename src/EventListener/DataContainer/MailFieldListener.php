<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use SeKultur\ContaoKulturnetzBundle\Service\Mail\MemberMailer;

/**
 * Felder "E-Mail Betreff" und "E-Mail Inhalt" der Freischaltungsmail in den
 * Masken der Profile und Veranstaltungen (bisher loadsubject(),
 * loadmailtext() und dontsaveempty() in den fünf DCA-Dateien).
 *
 * Beim Laden stehen immer die Vorschlagstexte des MemberMailer im Feld, beim
 * Speichern wird nichts gespeichert (die Felder haben keine Spalte).
 */
final class MailFieldListener
{
    public function __construct(private readonly MemberMailer $mailer)
    {
    }

    #[AsCallback(table: 'tl_artists', target: 'fields.sendmailsubject.load')]
    #[AsCallback(table: 'tl_hosts', target: 'fields.sendmailsubject.load')]
    #[AsCallback(table: 'tl_locations', target: 'fields.sendmailsubject.load')]
    #[AsCallback(table: 'tl_events', target: 'fields.sendmailsubject.load')]
    #[AsCallback(table: 'tl_sekevents', target: 'fields.sendmailsubject.load')]
    public function loadSubject(mixed $value, DataContainer $dc): string
    {
        return $this->mailer->getDefaultSubject(substr($dc->table, 3));
    }

    #[AsCallback(table: 'tl_artists', target: 'fields.sendmailtext.load')]
    #[AsCallback(table: 'tl_hosts', target: 'fields.sendmailtext.load')]
    #[AsCallback(table: 'tl_locations', target: 'fields.sendmailtext.load')]
    #[AsCallback(table: 'tl_events', target: 'fields.sendmailtext.load')]
    #[AsCallback(table: 'tl_sekevents', target: 'fields.sendmailtext.load')]
    public function loadText(mixed $value, DataContainer $dc): string
    {
        $record = $dc->getCurrentRecord() ?? [];

        return $this->mailer->getDefaultText(substr($dc->table, 3), (int) ($record['member_id'] ?? 0), (string) ($record['alias'] ?? ''));
    }

    #[AsCallback(table: 'tl_artists', target: 'fields.sendmailsubject.save')]
    #[AsCallback(table: 'tl_hosts', target: 'fields.sendmailsubject.save')]
    #[AsCallback(table: 'tl_locations', target: 'fields.sendmailsubject.save')]
    #[AsCallback(table: 'tl_events', target: 'fields.sendmailsubject.save')]
    #[AsCallback(table: 'tl_sekevents', target: 'fields.sendmailsubject.save')]
    #[AsCallback(table: 'tl_artists', target: 'fields.sendmailtext.save')]
    #[AsCallback(table: 'tl_hosts', target: 'fields.sendmailtext.save')]
    #[AsCallback(table: 'tl_locations', target: 'fields.sendmailtext.save')]
    #[AsCallback(table: 'tl_events', target: 'fields.sendmailtext.save')]
    #[AsCallback(table: 'tl_sekevents', target: 'fields.sendmailtext.save')]
    public function doNotSave(): string
    {
        return '';
    }
}
