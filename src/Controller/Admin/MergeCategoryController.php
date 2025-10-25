<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Enum\Role;
use App\Service\CategoryMergeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(
    '/admin-tool/merge-category',
    name: 'admin_merge_category',
    methods: ['POST']
)]
#[IsGranted(Role::ADMIN->value)]
class MergeCategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryMergeService $categoryMergeService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $sourceIds = $request->request->all('source_categories');
        $targetId  = (int) $request->request->get('target_category');

        try {
            $this->categoryMergeService->validateMergeRequestParam($sourceIds, $targetId);
            $result = $this->categoryMergeService->mergeParentCategories($sourceIds, $targetId);

            $this->addFlash('success', sprintf(
                'Fusion réussie : %d questions déplacées vers la catégorie cible. %d catégories supprimées.',
                $result['children_moved'],
                $result['categories_deleted']
            ));
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de la fusion : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_category_utility_1');
    }
}
