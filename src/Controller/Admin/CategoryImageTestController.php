<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Enum\Role;
use App\Service\CategoryImageGenerationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(
    '/admin/image-generation/category/{id}/image',
    name: 'category_test_image_generation',
    methods: ['GET']
)]
#[IsGranted(Role::ADMIN->value)]
class CategoryImageTestController extends AbstractController
{
    public function __construct(
        private readonly CategoryImageGenerationService $imageGenerationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        Request $request,
        Category $category,
    ): JsonResponse {
        try {
            // Vérifier si la génération est possible
            if (!$this->imageGenerationService->canGenerateImageFor($category)) {
                return $this->json([
                    'success'  => false,
                    'error'    => 'Impossible de générer une image pour cette catégorie',
                    'category' => [
                        'id'         => $category->getId(),
                        'name'       => $category->getName(),
                        'deleted_at' => $category->getDeletedAt()?->format('Y-m-d H:i:s'),
                    ],
                ], Response::HTTP_BAD_REQUEST);
            }

            // Récupérer le générateur demandé depuis les paramètres de requête
            $generatorName = $request->query->get('generator', 'mistral');

            $this->logger->info('Début de génération d\'image pour catégorie', [
                'category_id'          => $category->getId(),
                'category_name'        => $category->getName(),
                'category_description' => $category->getDescription(),
                'requested_generator'  => $generatorName,
            ]);

            // Tenter la génération
            $startTime = microtime(true);
            $success   = $this->imageGenerationService->generateAndAssignImage($category->getId(), $generatorName);
            $duration  = round((microtime(true) - $startTime) * 1000, 2); // en ms

            if (!$success) {
                $this->logger->error('Échec de génération d\'image', [
                    'category_id' => $category->getId(),
                    'duration_ms' => $duration,
                    'generator'   => $generatorName,
                ]);

                return $this->json([
                    'success'             => false,
                    'error'               => 'Échec de la génération d\'image',
                    'duration_ms'         => $duration,
                    'generator_requested' => $generatorName,
                    'category'            => [
                        'id'          => $category->getId(),
                        'name'        => $category->getName(),
                        'description' => $category->getDescription(),
                    ],
                    'suggestion' => 'Vérifiez les logs pour plus de détails ou essayez un autre générateur',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $this->logger->info('Image générée avec succès', [
                'category_id' => $category->getId(),
                'duration_ms' => $duration,
                'image_name'  => $category->getImageName(),
                'generator'   => $generatorName,
            ]);

            return $this->json([
                'success'        => true,
                'message'        => 'Image générée avec succès',
                'duration_ms'    => $duration,
                'generator_used' => $generatorName,
                'category'       => [
                    'id'          => $category->getId(),
                    'name'        => $category->getName(),
                    'description' => $category->getDescription(),
                    'image_name'  => $category->getImageName(),
                    'image_url'   => $category->getImageName()
                        ? '/uploads/images/categories/' . $category->getImageName()
                        : null,
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur inattendue lors de la génération d\'image', [
                'category_id' => $category->getId(),
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            return $this->json([
                'success'        => false,
                'error'          => 'Erreur inattendue: ' . $e->getMessage(),
                'category_id'    => $category->getId(),
                'exception_type' => get_class($e),
                'file'           => $e->getFile(),
                'line'           => $e->getLine(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
