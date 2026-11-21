<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\Member;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * E-Mail-Adresse als Benutzername der Mitglieder (Entscheidung E04).
 *
 * Ersatz für terminal42/contao-mailusername 2.2.0
 * (src/EventListener/MailUsernameListener.php) mit gleicher Logik und gleichen
 * Meldungen, aber ohne Contao\Input:
 *  - Registrierung: Der Benutzername wird aus der E-Mail-Adresse gesetzt, das
 *    Mitglied muss keinen Benutzernamen angeben (Hook createNewUser und
 *    onload-Callback für das Registrierungsformular).
 *  - Jede Änderung der E-Mail-Adresse (Backend, "Persönliche Daten" im Frontend)
 *    setzt den Benutzernamen gleich, mit Prüfung auf Eindeutigkeit.
 *
 * Die Feldeinstellungen in tl_member (E-Mail eindeutig, Benutzername deaktiviert,
 * nicht Pflicht, Regel E-Mail) und die Beschriftung "E-Mail-Adresse" für den
 * Benutzernamen gehören in die DCA- bzw. Sprachdatei des Bundles.
 *
 * Solange terminal42/contao-mailusername installiert ist, übernimmt dessen
 * Listener die Arbeit und dieser bleibt untätig; so entstehen keine doppelten
 * Fehlermeldungen (das Formular "Persönliche Daten" zeigt jede Exception eines
 * save-Callbacks einzeln an). Nach dem Entfernen des Pakets ist dieser Listener
 * allein zuständig.
 */
final class MailUsernameListener
{
    private const VENDOR_BUNDLE = 'Terminal42MailusernameBundle';

    /**
     * @param array<string, class-string> $kernelBundles
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
        private readonly ContaoFramework $framework,
        #[Autowire(param: 'kernel.bundles')]
        private readonly array $kernelBundles,
    ) {
    }

    /**
     * Registrierung: Benutzername = E-Mail-Adresse, falls keiner angegeben ist.
     *
     * @param array<string, mixed> $data
     */
    #[AsHook('createNewUser', priority: 100)]
    public function onCreateNewUser(int|string $id, array &$data): void
    {
        if ($this->isDelegated() || !empty($data['username'])) {
            return;
        }

        $data['username'] = $data['email'];
        $this->setPostValue('username', $data['email']);

        $this->saveUsername($data['email'] ?? null, (int) $id);

        // Modell im Register aktualisieren, damit die Version den Benutzernamen enthält
        // (Problem #7 des Originalpakets).
        $this->framework->initialize();
        $this->framework->getAdapter(MemberModel::class)->findById((int) $id)?->refresh();
    }

    /**
     * save-Callback des Feldes tl_member.email.
     *
     * Ohne Datensatz-Bezug ($dc = null, Registrierung) wird nur geprüft, ob die
     * Adresse schon als Benutzername vergeben ist. Sonst wird der Benutzername auf
     * die neue Adresse gesetzt (leer: NULL).
     */
    #[AsCallback('tl_member', 'fields.email.save')]
    public function onSaveEmail(string|null $value, object|null $dc): string|null
    {
        if ($this->isDelegated()) {
            return $value;
        }

        if (null === $dc) {
            $exists = $this->connection->fetchOne('SELECT TRUE FROM tl_member WHERE username = ?', [$value]);

            if (false !== $exists) {
                throw new \RuntimeException($this->translator->trans('ERR.unique', [], 'contao_default'));
            }

            return $value;
        }

        $this->saveUsername($value, (int) ($dc->id ?? 0));

        return $value;
    }

    /**
     * onload-Callback: Im Registrierungsformular (Aufruf ohne Argumente, Formular
     * "tl_registration...") wird der fehlende Benutzername aus der E-Mail-Adresse
     * übernommen, damit die Pflichtprüfung des Moduls greift wie mit Benutzername.
     */
    #[AsCallback('tl_member', 'config.onload')]
    public function onLoad(mixed ...$arguments): void
    {
        if ([] !== $arguments || $this->isDelegated()) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        $post = $request->request->all();

        if (\array_key_exists('username', $post) || !str_starts_with((string) ($post['FORM_SUBMIT'] ?? ''), 'tl_registration')) {
            return;
        }

        $this->setPostValue('username', $post['email'] ?? null);
    }

    /**
     * Benutzername eines bestehenden Mitglieds gleich der E-Mail-Adresse setzen,
     * ohne Adresse auf NULL.
     */
    private function saveUsername(string|null $email, int $id): void
    {
        if (!$email) {
            $this->connection->update('tl_member', ['username' => null], ['id' => $id], ['id' => ParameterType::INTEGER]);

            return;
        }

        $this->updateUsername($email, $id);
    }

    /**
     * Setzt den Benutzernamen eines bestehenden Mitglieds, gesperrt gegen parallele
     * Änderungen; wirft die Contao-Meldung "ERR.unique", wenn ein anderes Mitglied
     * den Namen bereits nutzt.
     */
    private function updateUsername(string $value, int $id): void
    {
        try {
            $this->connection->executeStatement('LOCK TABLES tl_member WRITE');

            $exists = $this->connection->fetchOne(
                'SELECT TRUE FROM tl_member WHERE username = ? AND id != ?',
                [$value, $id],
                [ParameterType::STRING, ParameterType::INTEGER],
            );

            if (false !== $exists) {
                throw new \RuntimeException($this->translator->trans('ERR.unique', [], 'contao_default'));
            }

            $this->connection->update('tl_member', ['username' => $value], ['id' => $id], ['id' => ParameterType::INTEGER]);
        } finally {
            $this->connection->executeStatement('UNLOCK TABLES');
        }
    }

    /**
     * Schreibt einen POST-Wert in den aktuellen Request. Contao\Input::post() liest
     * $request->request, die Legacy-Module sehen den Wert damit wie bisher über
     * Input::setPost().
     */
    private function setPostValue(string $key, mixed $value): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        if (null === $value) {
            $request->request->remove($key);

            return;
        }

        $request->request->set($key, $value);
    }

    /**
     * True, solange terminal42/contao-mailusername installiert ist (dessen
     * Listener erledigt dann dieselbe Arbeit).
     */
    private function isDelegated(): bool
    {
        return isset($this->kernelBundles[self::VENDOR_BUNDLE]);
    }
}
