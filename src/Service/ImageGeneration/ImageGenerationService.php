<?php

declare(strict_types=1);

namespace App\Service\ImageGeneration;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;

readonly class ImageGenerationService
{
    public function __construct(
        private ImageGeneratorRegistry $generatorRegistry,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Génère une image à partir d'un prompt en utilisant le générateur spécifié ou par défaut.
     *
     * @throws \Exception
     */
    public function generateImage(string $prompt, string $generatorName): ?File
    {
        try {
            $generator = $this->generatorRegistry->getGenerator($generatorName);


            $this->logger->info('Début de génération d\'image', [
                'prompt'    => $prompt,
                'generator' => $generator->getName(),
            ]);

            $imageFile = $generator->generateImage($prompt);

            if (!$imageFile) {
                $this->logger->error('Échec de génération d\'image', [
                    'generator' => $generator->getName(),
                ]);

                return null;
            }

            $this->logger->info('Image générée avec succès', [
                'generator' => $generator->getName(),
                'file_path' => $imageFile->getPathname(),
            ]);

            return $imageFile;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération d\'image', [
                'prompt'    => $prompt,
                'generator' => $generatorName,
                'error'     => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Retourne les informations sur les générateurs disponibles.
     *
     * @return array<int, array{name: string, available: bool}>
     */
    public function getGeneratorsInfo(): array
    {
        $info = [];
        foreach ($this->generatorRegistry->getAllGenerators() as $generator) {
            $info[] = [
                'name'      => $generator->getName(),
                'available' => $generator->isAvailable(),
            ];
        }

        return $info;
    }

    /**
     * Vérifie si au moins un générateur est disponible.
     */
    public function hasAvailableGenerator(): bool
    {
        return count($this->generatorRegistry->getAvailableGenerators()) > 0;
    }
}
