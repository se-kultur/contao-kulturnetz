<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Mail;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;
use SeKultur\ContaoKulturnetzBundle\Service\Page\PageUrlResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Freischaltungsmail an das Mitglied aus dem Backend (Felder "E-Mail senden?",
 * "E-Mail Betreff", "E-Mail Inhalt" der Profile und Veranstaltungen).
 *
 * Ersetzt die fünf Kopien von loadsubject(), loadmailtext() und mailing() in den
 * DCA-Dateien; Vorschlagstexte, Fehlermeldungen und Protokolltext sind wortgleich.
 * Absender ist wie bisher die Systemadresse (Einstellungen, adminEmail), der
 * Versand läuft über den Symfony Mailer (MAILER_DSN). Der Link im Vorschlagstext
 * kommt aus dem PageUrlResolver (Detailseite laut Konfiguration, URL-Endung aus
 * der Seitenkonfiguration) statt aus "https://" plus Host.
 */
final class MemberMailer
{
    public const MESSAGE_NO_MEMBER_EMAIL = 'Die E-Mail wurde nicht gesendet: Dem Datensatz ist kein Mitglied mit E-Mail-Adresse zugeordnet.';

    public const MESSAGE_MISSING_FIELDS = 'Die E-Mail wurde nicht gesendet und nichts gespeichert: Zu jedem Datensatz mit "E-Mail senden?" müssen "E-Mail Betreff" und "E-Mail Inhalt" ausgefüllt sein.';

    public const MESSAGE_TRANSPORT_FAILED = 'Die E-Mail konnte nicht gesendet werden. Details stehen im Systemlog.';

    private const SUBJECTS = [
        'artists' => 'Ihr SE-KulturNetz Künstler:innenprofil wurde aktiviert',
        'hosts' => 'Ihr SE-KulturNetz Veranstalter:innenprofil wurde aktiviert',
        'locations' => 'Ihr SE-KulturNetz Locationprofil wurde aktiviert',
        'events' => 'Ihre Veranstaltung wurde überprüft',
        'sekevents' => 'Ihr SE-KulturTage Event wurde überprüft',
    ];

    /**
     * Textbausteine je Typ: [Satz nach der Anrede, Einleitung des Links, Zusatz vor dem Gruß].
     */
    private const TEXTS = [
        'artists' => ['Ihr Künstler:innenprofil im SE-KulturNetz wurde aktiviert - herzlichen Glückwunsch!', 'Es ist nun über unsere Webseite öffentlich erreichbar:', ''],
        'hosts' => ['Ihr Veranstalter:innenprofil im SE-KulturNetz wurde aktiviert - herzlichen Glückwunsch!', 'Es ist nun über unsere Webseite öffentlich erreichbar:', ''],
        'locations' => ['Ihr Locationprofil im SE-KulturNetz wurde aktiviert - herzlichen Glückwunsch!', 'Es ist nun über unsere Webseite öffentlich erreichbar:', ''],
        'events' => ['Ihre Veranstaltung im SE-KulturNetz wurde überprüft und freigeschaltet - herzlichen Glückwunsch!', 'Sie können Ihre Veranstaltung über folgenden Link erreichen:', ''],
        'sekevents' => ['Ihr Event in den SE-KulturTagen wurde überprüft und freigeschaltet - herzlichen Glückwunsch!', 'Sie können Ihr Event über folgenden Link erreichen:', "Hinweis: Änderungen können ab sofort nur noch durch das SE-KulturTeam vorgenommen werden.\r\n\r\n"],
    ];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly PageUrlResolver $pageUrlResolver,
        #[Autowire(service: 'monolog.logger.contao.email')]
        private readonly LoggerInterface $emailLogger,
        #[Autowire(service: 'monolog.logger.contao.error')]
        private readonly LoggerInterface $errorLogger,
    ) {
    }

    /**
     * Vorschlag für den Betreff (bisher loadsubject()).
     *
     * @param string $type artists, hosts, locations, events oder sekevents
     */
    public function getDefaultSubject(string $type): string
    {
        return self::SUBJECTS[$this->assertType($type)];
    }

    /**
     * Vorschlag für den Mailtext mit Anrede und Link auf die Detailseite (bisher
     * loadmailtext()). Zeilenumbrüche wie bisher als "\r\n".
     */
    public function getDefaultText(string $type, int $memberId, string $alias): string
    {
        [$sentence, $linkIntro, $note] = self::TEXTS[$this->assertType($type)];
        $member = $this->findMember($memberId);
        $url = $this->pageUrlResolver->getDetailUrl($type, $alias) ?? '';

        return 'Moin '.($member['firstname'] ?? '').' '.($member['lastname'] ?? '')."!\r\n\r\n"
            .$sentence."\r\n\r\n"
            .$linkIntro."\r\n".$url."\r\n\r\n"
            .$note
            ."Liebe Grüße\r\nIhr SE-KulturTeam";
    }

    /**
     * Sendet die Freischaltungsmail an die E-Mail-Adresse des Mitglieds.
     *
     * Wirft eine MemberMailException mit der bisherigen Meldung, wenn kein Mitglied
     * mit E-Mail-Adresse zugeordnet ist oder Betreff bzw. Text fehlen. Ein
     * Versandfehler wird protokolliert und ebenfalls als MemberMailException
     * gemeldet, damit die Redaktion ihn am Feld sieht.
     *
     * @param string $type artists, hosts, locations, events oder sekevents
     */
    public function sendActivationMail(string $type, int $memberId, string $subject, string $text): void
    {
        $this->assertType($type);

        if ('' === trim($subject) || '' === trim($text)) {
            throw new MemberMailException(self::MESSAGE_MISSING_FIELDS);
        }

        $member = $this->findMember($memberId);
        $address = trim((string) ($member['email'] ?? ''));

        if ('' === $address) {
            throw new MemberMailException(self::MESSAGE_NO_MEMBER_EMAIL);
        }

        [$senderName, $senderAddress] = $this->getSender();

        $email = (new Email())
            ->from(new Address($senderAddress, $senderName))
            ->returnPath($senderAddress)
            ->to($address)
            ->subject($subject)
            ->text($text, 'utf-8')
        ;

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            $this->errorLogger->error(
                \sprintf('Bestätigungsmail an %s konnte nicht gesendet werden (tl_%s): %s', $address, $type, $exception->getMessage()),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR), 'exception' => $exception],
            );

            throw new MemberMailException(self::MESSAGE_TRANSPORT_FAILED, 0, $exception);
        }

        $this->emailLogger->info(
            'Bestätigungsmail wurde an '.$address.' gesendet (tl_'.$type.')',
            ['contao' => new ContaoContext(__METHOD__, ContaoContext::EMAIL)],
        );
    }

    /**
     * Absender wie bisher Contao\Email ohne eigenen Absender im Backend: die
     * Systemadresse aus den Einstellungen, ggf. mit Namen ("Name [adresse]").
     *
     * @return array{0: string, 1: string} Name und Adresse
     */
    private function getSender(): array
    {
        $this->framework->initialize();

        $adminEmail = (string) $this->framework->getAdapter(Config::class)->get('adminEmail');

        if ('' === $adminEmail) {
            throw new MemberMailException('Die E-Mail wurde nicht gesendet: In den Einstellungen ist keine E-Mail-Adresse des Systemadministrators hinterlegt.');
        }

        [$name, $address] = $this->framework->getAdapter(StringUtil::class)->splitFriendlyEmail($adminEmail);

        return [(string) $name, (string) $address];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findMember(int $memberId): array|null
    {
        if ($memberId < 1) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT firstname, lastname, email FROM tl_member WHERE id = ?',
            [$memberId],
            [ParameterType::INTEGER],
        );

        return false === $row ? null : $row;
    }

    private function assertType(string $type): string
    {
        if (!isset(self::SUBJECTS[$type])) {
            throw new \InvalidArgumentException(\sprintf('Unbekannter KulturNetz-Typ "%s".', $type));
        }

        return $type;
    }
}
