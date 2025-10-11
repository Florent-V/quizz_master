<?php

declare(strict_types=1);

namespace App\Service\ImageGeneration;

use Symfony\Component\HttpFoundation\File\File;

interface ImageGeneratorInterface
{
    /**
     * Checks if this policy supports the given image generator name.
     */
    public function supports(string $name): bool;

    /**
     * Returns the name of the image generator (e.g., "Gemini", "Mistral").
     */
    public function getName(): string;

    /**
     * Generates an image from a text prompt.
     *
     * @param string $prompt The text description for the image to generate
     *
     * @return File|null The generated image file, or null on failure
     */
    public function generateImage(string $prompt): ?File;

    /**
     * Checks if the image generator is available (e.g., API key is set).
     */
    public function isAvailable(): bool;
}
