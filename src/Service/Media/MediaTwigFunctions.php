<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Media;

use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * Twig-Funktionen der Medien- und Formular-Partials (kulturnetz/media/*,
 * kulturnetz/form/*).
 *
 * - kulturnetz_media_settings(): Einstellungen, die Server und Browser teilen
 *   (Kantenlänge des Zuschnitts, zulässige MIME-Typen, Größengrenze), dazu die
 *   Basis-URL für Dateipfade der Endpunkt-Antworten ("files/…") und die URL des
 *   Ersatzbildes. So steht kein Wert doppelt im JavaScript.
 *
 * Die allgemeine Funktion kulturnetz_asset() steht in Twig\KulturnetzExtension.
 */
final class MediaTwigFunctions
{
    public function __construct(
        private readonly KulturnetzConfig $config,
        private readonly MediaUploadValidator $validator,
        private readonly RequestStack $requestStack,
        #[Autowire(param: 'kulturnetz.image.placeholder')]
        private readonly string $placeholderPath,
    ) {
    }

    /**
     * @return array{crop_size: int, mime_types: list<string>, max_file_size: int|null, files_base: string, placeholder_src: string}
     */
    #[AsTwigFunction('kulturnetz_media_settings')]
    public function settings(): array
    {
        $basePath = (string) $this->requestStack->getMainRequest()?->getBasePath().'/';

        return [
            'crop_size' => MediaUploadValidator::CROP_SIZE,
            'mime_types' => $this->validator->getAllowedMimeTypes(),
            'max_file_size' => $this->config->getUploadMaxFileSize(),
            'files_base' => $basePath,
            'placeholder_src' => $basePath.ltrim($this->placeholderPath, '/'),
        ];
    }
}
