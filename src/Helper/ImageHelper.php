<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Helper;

use Contao\System;

class ImageHelper
{
    /**
     * Extrahiert den Dateipfad aus einem FilesModel oder gibt den Fallback zurück.
     */
    public static function avatarPath($avatarFile, string $fallback = 'files/se-kultur.de/img/noavatar.jpg'): string
    {
        if (is_object($avatarFile) && isset($avatarFile->path)) {
            return $avatarFile->path ?: $fallback;
        }

        return $fallback;
    }

    public static function resize(string $path, int $width, int $height, string $mode = 'crop'): ?string
    {
        if (!$path) {
            return null;
        }

        try {
            $container = System::getContainer();
            $rootDir = $container->getParameter('kernel.project_dir');
            $imageFactory = $container->get('contao.image.image_factory');
            $image = $imageFactory->create($rootDir . '/' . $path, [$width, $height, $mode]);

            return $image->getUrl($rootDir);
        } catch (\Exception $e) {
            if (isset($container)) {
                $container->get('monolog.logger.contao.error')->error(
                    'Image "' . $path . '" could not be resized: ' . $e->getMessage()
                );
            }

            return null;
        }
    }
}
