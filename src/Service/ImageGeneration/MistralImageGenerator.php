<?php

declare(strict_types=1);

namespace App\Service\ImageGeneration;

use App\Service\MistralApiService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;

class MistralImageGenerator implements ImageGeneratorInterface
{
    private const string GENERATED_IMAGES_TEMP_DIR = '/tmp/generated_images';

    private const string NAME = 'mistral';

    public function __construct(
        private readonly MistralApiService $mistralApiService,
        private readonly LoggerInterface $logger,
        private readonly string $mistralApiKey,
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
        return !empty($this->mistralApiKey);
    }

    public function generateImage(string $prompt): ?File
    {
        try {
            $this->logger->info('Génération d\'image avec Mistral AI', [
                'prompt' => $prompt,
            ]);

            // Utiliser le workflow Mistral pour générer l'image
            $result = $this->mistralApiService->generateImageWorkflow($prompt);

            if (!$result || !isset($result['image_content'])) {
                $this->logger->error('Aucune image générée par Mistral AI', [
                    'result' => $result,
                ]);

                return null;
            }

            // Sauvegarder l'image
            $filename = sprintf(
                'mistral_%s.%s',
                uniqid(),
                $result['file_type'] ?? 'png'
            );
            $filepath = self::GENERATED_IMAGES_TEMP_DIR . '/' . $filename;

            file_put_contents($filepath, $result['image_content']);

            $this->logger->info('Image générée avec succès par Mistral AI', [
                'file_path' => $filepath,
                'file_size' => strlen($result['image_content']),
            ]);

            return new File($filepath);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération d\'image avec Mistral AI', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
