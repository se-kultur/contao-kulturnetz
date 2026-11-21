<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Twig;

use Contao\CoreBundle\Image\Studio\Figure;
use Contao\FilesModel;
use Psr\Clock\ClockInterface;
use SeKultur\ContaoKulturnetzBundle\Service\Image\FigureFactory;
use SeKultur\ContaoKulturnetzBundle\Service\Image\ImageSize;
use SeKultur\ContaoKulturnetzBundle\Service\Page\PageUrlResolver;
use SeKultur\ContaoKulturnetzBundle\Service\Page\SignupWindowChecker;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileCategories;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * Twig-Funktionen und -Filter des KulturNetz-Bundles.
 *
 * Funktionen:
 *  - kulturnetz_signup_open(): Anmeldelinks der SE-KulturTage sichtbar?
 *  - kulturnetz_page_url(seitenschlüssel, parameter = null, absolut = false):
 *    Link auf eine KulturNetz-Seite (Schlüssel siehe Service\Page\PageUrlResolver)
 *  - kulturnetz_profile_categories(): Kategorien der Suchformulare (Wert => Text)
 *  - kulturnetz_figure(quelle, größe, ersatzbild = true): Figure für figure-Komponenten
 *  - kulturnetz_image_url(quelle, größe, ersatzbild = true): URL eines skalierten Bildes
 *
 * Filter:
 *  - kulturnetz_time_ago: relative Zeitangabe eines Zeitstempels ("Vor 3 Stunden")
 *  - kulturnetz_sparten: Sparten-Liste in Großbuchstaben, getrennt durch " | "
 *  - kulturnetz_url_host: Host einer Webseitenangabe ("www.beispiel.de")
 *  - kulturnetz_external_url: Webseitenangabe als Link-Ziel mit "https://"
 *
 * Größen sind die Namen aus Service\Image\ImageSize ohne Präfix, z. B. "list"
 * oder "kulturnetz_list".
 */
final class KulturnetzExtension
{
    public function __construct(
        private readonly SignupWindowChecker $signupWindowChecker,
        private readonly PageUrlResolver $pageUrlResolver,
        private readonly FigureFactory $figureFactory,
        private readonly ProfileCategories $profileCategories,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Gleiche Logik wie bisher Helper\AnmeldungHelper::linksOffen().
     */
    #[AsTwigFunction('kulturnetz_signup_open')]
    public function signupOpen(): bool
    {
        return $this->signupWindowChecker->isOpen();
    }

    /**
     * Pfad (bzw. absolute URL) einer KulturNetz-Seite, optional mit dem Alias eines
     * Datensatzes. Beispiel: kulturnetz_page_url('artists.detail', artist.alias).
     * Liefert null, wenn die Seite fehlt.
     */
    #[AsTwigFunction('kulturnetz_page_url')]
    public function pageUrl(string $pageKey, string|null $parameter = null, bool $absolute = false): string|null
    {
        return $this->pageUrlResolver->generate(
            $pageKey,
            $parameter,
            $absolute ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH,
        );
    }

    /**
     * @return array<string, string>
     */
    #[AsTwigFunction('kulturnetz_profile_categories')]
    public function profileCategories(): array
    {
        return $this->profileCategories->options();
    }

    #[AsTwigFunction('kulturnetz_figure')]
    public function figure(FilesModel|string|null $source, string $size, bool $usePlaceholder = true): Figure|null
    {
        return $this->figureFactory->createFigure($source, $this->imageSize($size), $usePlaceholder);
    }

    #[AsTwigFunction('kulturnetz_image_url')]
    public function imageUrl(FilesModel|string|null $source, string $size, bool $usePlaceholder = true): string|null
    {
        return $this->figureFactory->getImageUrl($source, $this->imageSize($size), $usePlaceholder);
    }

    /**
     * Relative Zeitangabe, wortgleich zu PostingsModel::timeAgo(): unter einer
     * Minute "Vor wenigen Sekunden", dann "Vor N Minuten/Stunden/Tagen/Wochen"
     * (aufgerundet), ab vier Wochen "Am TT.MM.JJJJ, HH:MM Uhr".
     */
    #[AsTwigFilter('kulturnetz_time_ago')]
    public function timeAgo(int|string|null $timestamp): string
    {
        $timestamp = (int) $timestamp;
        $diff = $this->clock->now()->getTimestamp() - $timestamp;

        return match (true) {
            $diff < 60 => 'Vor wenigen Sekunden',
            $diff < 3600 => 'Vor '.ceil($diff / 60).' Minuten',
            $diff < 86400 => 'Vor '.ceil($diff / 3600).' Stunden',
            $diff < 604800 => 'Vor '.ceil($diff / 86400).' Tagen',
            $diff < 2419200 => 'Vor '.ceil($diff / 604800).' Wochen',
            default => 'Am '.date('d.m.Y, H:i', $timestamp).' Uhr',
        };
    }

    /**
     * Sparten bzw. Zielgruppen wie bisher in den Templates:
     * trim(strtoupper(str_replace(',', ' | ', $wert))). strtoupper() wandelt wie
     * bisher nur ASCII-Buchstaben um.
     */
    #[AsTwigFilter('kulturnetz_sparten')]
    public function sparten(string|null $value): string
    {
        return trim(strtoupper(str_replace(',', ' | ', (string) $value)));
    }

    /**
     * Host einer Webseitenangabe wie bisher parse_url('https://'.$website, PHP_URL_HOST);
     * eine Angabe mit Schema wird nicht doppelt ergänzt. Gespeicherte Entities
     * (Contao-Speicherformat) werden vorher dekodiert.
     */
    #[AsTwigFilter('kulturnetz_url_host')]
    public function urlHost(string|null $value): string
    {
        return (string) parse_url($this->externalUrl($value), PHP_URL_HOST);
    }

    /**
     * Link-Ziel einer Webseitenangabe wie bisher "https://" plus Angabe; eine
     * Angabe mit Schema (http, https) bleibt unverändert. Leer bleibt leer.
     */
    #[AsTwigFilter('kulturnetz_external_url')]
    public function externalUrl(string|null $value): string
    {
        $value = trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ('' === $value) {
            return '';
        }

        return preg_match('~^https?://~i', $value) ? $value : 'https://'.$value;
    }

    private function imageSize(string $size): ImageSize
    {
        $name = str_starts_with($size, '_') ? substr($size, 1) : $size;

        return ImageSize::tryFrom($name)
            ?? ImageSize::tryFrom('kulturnetz_'.$name)
            ?? throw new \InvalidArgumentException(\sprintf('Unbekannte KulturNetz-Bildgröße "%s".', $size));
    }
}
