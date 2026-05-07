<?php

namespace App\Service;

/**
 * Résultat de la génération : image fusionnée côté serveur (GD) ou superposition à faire côté navigateur.
 */
final class GeneratedCardResult
{
    public function __construct(
        public ?string $composedImageDataUrl,
        public string $themeImageUrl,
        public bool $requiresClientSideLayers,
    ) {
    }
}
