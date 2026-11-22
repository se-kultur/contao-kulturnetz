<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write;

use Contao\CoreBundle\Routing\PageFinder;
use Contao\CoreBundle\Util\LocaleUtil;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Meldungen der Mitglieder-Endpunkte (Anlegen, Bearbeiten, Dateien) aus den
 * Sprachdateien des Bundles (contao/languages/<Sprache>/default.php, Schlüssel
 * $GLOBALS['TL_LANG']['ERR'][<Schlüssel>]).
 *
 * Die Endpunkte unter /_kulturnetz gehören zu keiner Seite; Contao wählt dort die
 * Sprache sonst nach dem Accept-Language-Kopf des Browsers. Die Meldungen
 * erscheinen aber im Formular der Website, deshalb gilt die Sprache des
 * Startpunkts der Website (Wurzelseite zum Host), wie bei den Seiten selbst.
 */
final class FormMessages
{
    private const DOMAIN = 'contao_default';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
        private readonly PageFinder $pageFinder,
    ) {
    }

    /**
     * @param list<string|int> $parameters Platzhalter der Meldung (sprintf)
     */
    public function get(string $key, array $parameters = []): string
    {
        return $this->translator->trans('ERR.'.$key, $parameters, self::DOMAIN, $this->getLocale());
    }

    private function getLocale(): string|null
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return null;
        }

        $language = $this->pageFinder->findRootPageForRequest($request)?->language;

        return \is_string($language) && '' !== $language ? LocaleUtil::formatAsLocale($language) : null;
    }
}
