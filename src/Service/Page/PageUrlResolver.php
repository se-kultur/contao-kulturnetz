<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Page;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\PageModel;
use Psr\Log\LoggerInterface;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Erzeugt die Links auf die Seiten des KulturNetzes aus den Seiten-Aliasen der
 * Bundle-Konfiguration (KulturnetzConfig) über Contao-Seitenmodelle und den
 * Content-URL-Generator.
 *
 * Ersetzt die relativen Links der Templates (z. B.
 * "detailansicht-kuenstlerprofil/<alias>.html"), die nur mit dem <base>-Tag
 * funktionieren, und die aus "https://" plus Host zusammengesetzten Links in den
 * Mails. Die URL-Endung (heute ".html") und ein eventuelles Sprachpräfix kommen
 * aus der Seitenkonfiguration der Wurzelseite, nicht mehr aus dem Template.
 *
 * Seitenschlüssel:
 *  - "<typ>.detail", "<typ>.edit", "<typ>.add", "<typ>.list" mit <typ> aus
 *    KulturnetzConfig::TYPES (artists, hosts, locations, events, sekevents),
 *  - "own_profiles", "own_events", "terms", "signup", "teaser".
 *
 * Fehlt eine Seite (z. B. gelöscht) oder lässt sich keine Route erzeugen, liefern
 * die Methoden null und protokollieren den Fehler im Contao-Systemlog; die Seite,
 * die den Link ausgibt, bleibt erreichbar. Ein unbekannter Seitenschlüssel ist
 * ein Programmierfehler und wirft eine \InvalidArgumentException.
 */
final class PageUrlResolver implements ResetInterface
{
    private const PURPOSES = ['detail', 'edit', 'add', 'list'];

    private const SINGLE_PAGES = ['own_profiles', 'own_events', 'terms', 'signup', 'teaser'];

    /**
     * @var array<string, PageModel|false>
     */
    private array $pageCache = [];

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ContentUrlGenerator $urlGenerator,
        private readonly KulturnetzConfig $config,
        #[Autowire(service: 'monolog.logger.contao.error')]
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Leert den Seiten-Zwischenspeicher zwischen zwei Requests (kernel.reset).
     */
    public function reset(): void
    {
        $this->pageCache = [];
    }

    /**
     * Alias der Seite zu einem Seitenschlüssel laut Konfiguration.
     */
    public function getPageAlias(string $pageKey): string
    {
        if (\in_array($pageKey, self::SINGLE_PAGES, true)) {
            return match ($pageKey) {
                'own_profiles' => $this->config->getOwnProfilesPageAlias(),
                'own_events' => $this->config->getOwnEventsPageAlias(),
                'terms' => $this->config->getTermsPageAlias(),
                'signup' => $this->config->getSignupPageAlias(),
                'teaser' => $this->config->getTeaserPageAlias(),
            };
        }

        $parts = explode('.', $pageKey, 2);

        if (2 !== \count($parts) || !\in_array($parts[1], self::PURPOSES, true)) {
            throw new \InvalidArgumentException(\sprintf('Unbekannter Seitenschlüssel "%s", erlaubt sind "<typ>.%s" und %s.', $pageKey, implode('|', self::PURPOSES), implode(', ', self::SINGLE_PAGES)));
        }

        [$type, $purpose] = $parts;

        return match ($purpose) {
            'detail' => $this->config->getDetailPageAlias($type),
            'edit' => $this->config->getEditPageAlias($type),
            'add' => $this->config->getAddPageAlias($type),
            'list' => $this->config->getListPageAlias($type),
        };
    }

    /**
     * Seitenmodell zu einem Seitenschlüssel (unabhängig vom Veröffentlichungsstatus,
     * wie das Insert-Tag {{link_url::<alias>}}), sonst null.
     */
    public function findPage(string $pageKey): PageModel|null
    {
        $alias = $this->getPageAlias($pageKey);

        if (!isset($this->pageCache[$alias])) {
            $this->framework->initialize();

            $page = $this->framework->getAdapter(PageModel::class)->findByIdOrAlias($alias);
            $this->pageCache[$alias] = $page instanceof PageModel ? $page : false;
        }

        return $this->pageCache[$alias] ?: null;
    }

    /**
     * URL einer Seite, optional mit einem Parameter (Alias des Datensatzes, wird
     * als auto_item "/<parameter>" angehängt).
     *
     * @param int $referenceType UrlGeneratorInterface::ABSOLUTE_PATH (Standard) oder ABSOLUTE_URL
     */
    public function generate(string $pageKey, string|null $parameter = null, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string|null
    {
        $page = $this->findPage($pageKey);

        if (null === $page) {
            $this->logger->error(
                \sprintf('KulturNetz: Seite "%s" (Schlüssel "%s") nicht gefunden, Link wird nicht ausgegeben.', $this->getPageAlias($pageKey), $pageKey),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)],
            );

            return null;
        }

        $parameters = null !== $parameter && '' !== $parameter ? ['parameters' => '/'.$parameter] : [];

        try {
            return $this->urlGenerator->generate($page, $parameters, $referenceType);
        } catch (ExceptionInterface $exception) {
            $this->logger->error(
                \sprintf('KulturNetz: Für die Seite "%s" (Schlüssel "%s") konnte keine URL erzeugt werden: %s', $page->alias, $pageKey, $exception->getMessage()),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR), 'exception' => $exception],
            );

            return null;
        }
    }

    /**
     * Absoluter Pfad der Detailseite eines Datensatzes, z. B.
     * "/detailansicht-kuenstlerprofil/<alias>.html".
     */
    public function getDetailPath(string $type, string $alias): string|null
    {
        return $this->generate($type.'.detail', $alias);
    }

    /**
     * Absolute URL (mit Schema und Host) der Detailseite eines Datensatzes, z. B. für Mails.
     */
    public function getDetailUrl(string $type, string $alias): string|null
    {
        return $this->generate($type.'.detail', $alias, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Absoluter Pfad der Bearbeiten-Seite eines Datensatzes.
     */
    public function getEditPath(string $type, string $alias): string|null
    {
        return $this->generate($type.'.edit', $alias);
    }

    /**
     * Absoluter Pfad der Anlegen-Seite eines Typs.
     */
    public function getAddPath(string $type): string|null
    {
        return $this->generate($type.'.add');
    }

    /**
     * Absoluter Pfad der öffentlichen Listenseite eines Typs.
     */
    public function getListPath(string $type): string|null
    {
        return $this->generate($type.'.list');
    }
}
