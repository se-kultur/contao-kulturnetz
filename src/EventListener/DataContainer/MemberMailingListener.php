<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use SeKultur\ContaoKulturnetzBundle\Service\Mail\MemberMailer;
use SeKultur\ContaoKulturnetzBundle\Service\Mail\MemberMailException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Feld "E-Mail senden?" in den Masken der Profile und Veranstaltungen: sendet
 * beim Speichern die Freischaltungsmail an das Mitglied (bisher mailing() in den
 * fünf DCA-Dateien).
 *
 * Ablauf wie unter Contao 4.13 (Entscheidung zu Frage Nr. 40, Variante a): Die
 * Mail geht im save_callback des Feldes hinaus, also vor der Prüfung der
 * übrigen Felder. Bei "Mehrere bearbeiten" heißen die Eingaben "<feld>_<id>";
 * dann werden zuerst alle zum Versand markierten Datensätze auf Betreff und
 * Text geprüft, bevor die erste Mail das System verlässt. Ein Fehler erscheint
 * am Feld "E-Mail senden?" und verhindert das Speichern.
 */
#[AsCallback(table: 'tl_artists', target: 'fields.sendmail.save')]
#[AsCallback(table: 'tl_hosts', target: 'fields.sendmail.save')]
#[AsCallback(table: 'tl_locations', target: 'fields.sendmail.save')]
#[AsCallback(table: 'tl_events', target: 'fields.sendmail.save')]
#[AsCallback(table: 'tl_sekevents', target: 'fields.sendmail.save')]
final class MemberMailingListener
{
    public function __construct(
        private readonly MemberMailer $mailer,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(mixed $value, DataContainer $dc): string
    {
        if ('1' !== (string) $value) {
            return '';
        }

        $post = $this->getPostValues();

        // Suffix der Eingabenamen: leer bei Einzelbearbeitung und "Alle
        // überschreiben", "_<id>" bei "Mehrere bearbeiten".
        $suffix = substr((string) $dc->inputName, \strlen((string) $dc->field));
        $suffixes = [$suffix];

        if ('' !== $suffix) {
            $suffixes = [];

            foreach ($post as $key => $postValue) {
                if (preg_match('/^sendmail(_\d+)$/', (string) $key, $match) && '1' === $postValue) {
                    $suffixes[] = $match[1];
                }
            }
        }

        foreach ($suffixes as $check) {
            if ('' === trim($post['sendmailsubject'.$check] ?? '') || '' === trim($post['sendmailtext'.$check] ?? '')) {
                throw new MemberMailException(MemberMailer::MESSAGE_MISSING_FIELDS);
            }
        }

        $record = $dc->getCurrentRecord() ?? [];

        $this->mailer->sendActivationMail(
            substr($dc->table, 3),
            (int) ($record['member_id'] ?? 0),
            $post['sendmailsubject'.$suffix] ?? '',
            $post['sendmailtext'.$suffix] ?? '',
        );

        return '';
    }

    /**
     * Rohwerte des Formulars (wie bisher Input::postUnsafeRaw()), nur Zeichenketten.
     *
     * @return array<string, string>
     */
    private function getPostValues(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return [];
        }

        return array_filter($request->request->all(), static fn (mixed $value): bool => \is_string($value));
    }
}
