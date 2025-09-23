<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ContrastColorExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('contrastColor', [$this, 'getContrastColor']),
        ];
    }

    public function getContrastColor(string $hexColor): string
    {
        // Convertit la couleur hexadécimale en RGB
        $r = hexdec(substr($hexColor, 0, 2));
        $g = hexdec(substr($hexColor, 2, 2));
        $b = hexdec(substr($hexColor, 4, 2));

        // Calcule la luminosité relative
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        // Retourne noir ou blanc selon la luminosité
        return $luminance > 0.5 ? '#000000' : '#FFFFFF';
    }
}
