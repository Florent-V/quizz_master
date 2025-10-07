<?php

declare(strict_types=1);

namespace App\Service\ImageGeneration;

use Gemini\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;

class GeminiImageGenerator implements ImageGeneratorInterface
{
    private const string GENERATED_IMAGES_TEMP_DIR = '/tmp/generated_images';

    private const string NAME = 'gemini';

    public function __construct(
        private readonly Client $geminiClient,
        private readonly LoggerInterface $logger,
        private readonly string $geminiApiKey,
    ) {
        // Créer le dossier temporaire s'il n'existe pas
        if (!is_dir(self::GENERATED_IMAGES_TEMP_DIR)) {
            mkdir(self::GENERATED_IMAGES_TEMP_DIR, 0755, true);
        }
    }

    public function supports(string $name): bool
    {
        return self::NAME === $name;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function isAvailable(): bool
    {
        return !empty($this->geminiApiKey);
    }

    public function generateImage(string $prompt): ?File
    {
        try {
            $this->logger->info('Génération d\'image avec Gemini AI', [
                'prompt' => $prompt,
            ]);

            // Utiliser Gemini avec génération d'images (Imagen)
            $response = $this->geminiClient
                ->generativeModel(model: 'gemini-2.5-flash-image')
                ->generateContent($prompt);

            // Récupérer l'image générée
            $parts = $response->parts();
            foreach ($parts as $part) {
                if (
                    $part->inlineData
                    && $part->inlineData->mimeType
                    && str_starts_with($part->inlineData->mimeType->value, 'image/')
                ) {
                    // Sauvegarder l'image générée par Gemini
                    return $this->saveGeneratedImage($part->inlineData->data, $part->inlineData->mimeType->value);
                }
            }

            $this->logger->warning('Aucune image générée par Gemini');

            return null;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération d\'image avec Gemini AI', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function saveGeneratedImage(string $imageData, string $mimeType): ?File
    {
        try {
            // Décoder les données base64
            $decodedData = base64_decode($imageData);

            // Déterminer l'extension
            $extension = match ($mimeType) {
                'image/png'  => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                default      => 'png',
            };

            // Créer un nom de fichier unique
            $filename = sprintf('gemini_%s.%s', uniqid(), $extension);
            $filepath = self::GENERATED_IMAGES_TEMP_DIR . '/' . $filename;

            // Sauvegarder l'image
            file_put_contents($filepath, $decodedData);

            $this->logger->info('Image générée avec succès par Gemini AI', [
                'file_path' => $filepath,
                'file_size' => strlen($decodedData),
            ]);

            return new File($filepath);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la sauvegarde de l\'image Gemini', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
