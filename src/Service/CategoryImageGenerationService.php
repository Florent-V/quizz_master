<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Quiz\Exception\QuizNotFoundException;
use App\Repository\CategoryRepository;
use App\Service\ImageGeneration\ImageGenerationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

readonly class CategoryImageGenerationService
{
    public function __construct(
        private ImageGenerationService $imageGenerationService,
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    //    private function createMockImageFile(): File
    //    {
    //        // Chemin vers une image de test existante dans public/images/
    //        $sourcePath = __DIR__ . '/../../public/images/image_generated_0.png';
    //
    //        if (!file_exists($sourcePath)) {
    //            throw new \RuntimeException('L\'image de mock n\'existe pas : ' . $sourcePath);
    //        }
    //
    //        // Créer une copie temporaire pour simuler une nouvelle image
    //        $filename = sprintf('mock_%s.png', uniqid('', true));
    //        $tempPath = sys_get_temp_dir() . '/' . $filename;
    //
    //        copy($sourcePath, $tempPath);
    //
    //        $this->logger->info('Image mock créée', [
    //            'source'    => $sourcePath,
    //            'temp_path' => $tempPath,
    //        ]);
    //
    //        return new File($tempPath);
    //    }

    /**
     * Génère et assigne une image à une catégorie.
     */
    public function generateAndAssignImage(int $categoryId, ?string $generatorName = 'mistral'): bool
    {
        try {
            // Récupérer la catégorie
            $category = $this->categoryRepository->find($categoryId);

            if (!$category instanceof Category) {
                throw new QuizNotFoundException('Catégorie non trouvée');
            }

            // Créer le prompt spécifique pour cette catégorie
            $prompt = $this->createPromptForCategory($category);

            // Génération via le service générique
            $imageFile = $this->imageGenerationService->generateImage($prompt, $generatorName);
            // use mock
            // $imageFile = $this->createMockImageFile();

            if (!$imageFile) {
                $this->logger->error('Impossible de générer une image pour la catégorie', [
                    'category_id'         => $category->getId(),
                    'category_name'       => $category->getName(),
                    'requested_generator' => $generatorName,
                ]);

                return false;
            }


            $this->logger->info('Fichier généré avant assignation', [
                'file_path'   => $imageFile->getPathname(),
                'file_size'   => $imageFile->getSize(),
                'file_exists' => file_exists($imageFile->getPathname()),
            ]);

            // Assigner l'image à la catégorie
            $category->setImageFile(new ReplacingFile($imageFile->getPathname()));

            // Persister la catégorie avec l'imageName mis à jour par VichUploader
            $this->entityManager->persist($category);
            $this->entityManager->flush();

            // dd($category);

            $this->logger->info('Image générée et assignée avec succès', [
                'category_id'   => $category->getId(),
                'category_name' => $category->getName(),
                'image_name'    => $category->getImageName(),
                'generator'     => $generatorName,
            ]);

            // Nettoyer le fichier temporaire après l'upload
            $tempFilePath = $imageFile->getPathname();

            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération et assignation d\'image', [
                'category_id' => $category->getId(),
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Vérifie si une catégorie peut avoir une image générée.
     */
    public function canGenerateImageFor(Category $category): bool
    {
        return null !== $category->getName()
            && ''   !== $category->getName()
            && null === $category->getDeletedAt()
            && $this->imageGenerationService->hasAvailableGenerator();
    }

    /**
     * Crée un prompt optimisé pour la génération d'image basé sur la catégorie.
     */
    private function createPromptForCategory(Category $category): string
    {
        $baseName    = $category->getName();
        $description = $category->getDescription();

        $prompt = "Create a realistic and high-quality image representing the quiz category '{$baseName}'. ";

        if ($description) {
            $prompt .= "Context: {$description}. ";
        }

        $prompt .= 'Style requirements: ';
        $prompt .= '- Realistic photographic image or detailed illustration ';
        $prompt .= '- Vibrant and attractive colors ';
        $prompt .= '- Centered and balanced composition ';
        $prompt .= '- Square format suitable for web interface ';
        $prompt .= '- Professional and engaging style ';
        $prompt .= '- High definition, sharp details ';

        $prompt .= 'Examples by theme: ';
        $prompt .= '- Football: football ball, player in action, stadium ';
        $prompt .= '- History: historical monument, historical scene, ancient artifact ';
        $prompt .= '- Automotive sports: race car, circuit, pilot ';
        $prompt .= '- Cooking: delicious dishes, fresh ingredients, chef ';
        $prompt .= '- Science: laboratory, experiment, scientific equipment ';
        $prompt .= '- Nature: natural landscape, animals, environment ';
        $prompt .= '- Technology: modern devices, innovation, futurism ';
        $prompt .= '- Art: artistic work, color palette, creation ';

        $prompt .= 'The image must be immediately recognizable and representative of the theme. ';
        $prompt .= 'Dimensions: 400x400 pixels. ';
        $prompt .= 'Quality: high definition, sharp details.';

        return $prompt;
    }
}
