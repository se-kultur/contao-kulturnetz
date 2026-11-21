<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Mail;

use Contao\CoreBundle\Monolog\ContaoContext;
use Psr\Log\LoggerInterface;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Service\Page\PageUrlResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Hinweismail an die Redaktion, wenn ein Mitglied ein Profil oder eine
 * Veranstaltung neu angelegt hat.
 *
 * Betreff und Text wortgleich zu den bisherigen Ajax-Skripten
 * (files/se-kultur.de/code/insert*.php). Absender und Empfänger kommen aus der
 * Bundle-Konfiguration (kulturnetz.mail.*, Empfänger und Blindkopie über die
 * Umgebungsvariablen KULTURNETZ_MAIL_EDITORIAL_RECIPIENT und
 * KULTURNETZ_MAIL_EDITORIAL_BCC), der Versand über den Symfony Mailer (MAILER_DSN).
 *
 * Ein Versandfehler wird abgefangen und im Contao-Systemlog protokolliert; der
 * Datensatz ist zu diesem Zeitpunkt bereits gespeichert, die Antwort an das
 * Mitglied bleibt deshalb ein Erfolg (bisher brach eine Exception die Antwort ab).
 */
final class NotificationMailer
{
    /**
     * Betreff und Satzteile je Typ: [Betreff, Satz "Es wurde ... angelegt.", Pronomen].
     */
    private const TEXTS = [
        'artists' => ['Neues Künstlerinnenprofil angelegt', 'Es wurde ein neues Künstlerprofil angelegt.', 'Es'],
        'hosts' => ['Neues Veranstalterinnenprofil angelegt', 'Es wurde ein neues Veranstalterinnenprofil angelegt.', 'Es'],
        'locations' => ['Neues Locationprofil angelegt', 'Es wurde ein neues Locationprofil angelegt.', 'Es'],
        'events' => ['Neue Veranstaltung angelegt', 'Es wurde eine neue Veranstaltung angelegt.', 'Diese'],
        'sekevents' => ['Neues SE-KulturTage-Event angelegt', 'Es wurde ein neues SE-KulturTage-Event angelegt.', 'Es'],
    ];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly KulturnetzConfig $config,
        private readonly PageUrlResolver $pageUrlResolver,
        #[Autowire(service: 'monolog.logger.contao.email')]
        private readonly LoggerInterface $emailLogger,
        #[Autowire(service: 'monolog.logger.contao.error')]
        private readonly LoggerInterface $errorLogger,
    ) {
    }

    /**
     * Sendet die Hinweismail zu einem neu angelegten Datensatz. Liefert true nach
     * erfolgreicher Übergabe an den Mailer, false, wenn kein Empfänger konfiguriert
     * ist oder der Versand scheitert (beides protokolliert).
     *
     * @param string $type  artists, hosts, locations, events oder sekevents
     * @param string $alias Alias des neuen Datensatzes
     */
    public function notifyNewRecord(string $type, string $alias): bool
    {
        if (!isset(self::TEXTS[$type])) {
            throw new \InvalidArgumentException(\sprintf('Unbekannter KulturNetz-Typ "%s".', $type));
        }

        $recipient = $this->config->getEditorialRecipientAddress();

        if ('' === $recipient) {
            $this->errorLogger->error(
                \sprintf('KulturNetz: Keine Redaktionsmail zum neuen Datensatz "%s" (%s), weil kein Empfänger konfiguriert ist (KULTURNETZ_MAIL_EDITORIAL_RECIPIENT).', $alias, $type),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)],
            );

            return false;
        }

        [$subject, $sentence, $pronoun] = self::TEXTS[$type];
        $url = $this->pageUrlResolver->getDetailUrl($type, $alias) ?? '';

        $html = 'Hallo!<br><br>'.$sentence.'<br><br>'.$pronoun.' ist über folgenden Link abrufbar:<br>'
            .htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            .'<br><br>Diese E-Mail wurde automatisch erstellt.<br>Bitte antworten Sie nicht darauf!';

        $text = "Hallo!\n\n".$sentence."\n\n".$pronoun." ist über folgenden Link abrufbar:\n".$url
            ."\n\nDiese E-Mail wurde automatisch erstellt. Bitte antworten Sie nicht darauf!";

        $email = (new Email())
            ->from(new Address($this->config->getMailSenderAddress(), $this->config->getMailSenderName()))
            ->to(new Address($recipient, $this->config->getEditorialRecipientName()))
            ->subject($subject)
            ->html($html, 'utf-8')
            ->text($text, 'utf-8')
        ;

        foreach ($this->config->getEditorialBccAddresses() as $bcc) {
            $email->addBcc($bcc);
        }

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            $this->errorLogger->error(
                \sprintf('KulturNetz: Redaktionsmail zum neuen Datensatz "%s" (%s) konnte nicht gesendet werden: %s', $alias, $type, $exception->getMessage()),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR), 'exception' => $exception],
            );

            return false;
        }

        $this->emailLogger->info(
            \sprintf('Redaktionsmail zum neuen Datensatz "%s" (tl_%s) wurde an %s gesendet', $alias, $type, $recipient),
            ['contao' => new ContaoContext(__METHOD__, ContaoContext::EMAIL)],
        );

        return true;
    }
}
