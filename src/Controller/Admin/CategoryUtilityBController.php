<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Service\CategoryService;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/cat-utility', name: 'admin_cat_utility_')]
class CategoryUtilityBController extends AbstractController
{
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $categorizedCategories = $this->categoryService->getCategorizedLists();
        $stats                 = $this->categoryService->getCategoryStats($categorizedCategories);
        foreach ($stats as &$stat) {
            $stat['question_list_url'] = $this->getQuestionListUrl($stat['category']);
        }
        unset($stat);

        return $this->render('admin/category_utility/index.html.twig', [
            'page_title'       => 'Gestion des catégories',
            'categories'       => $categorizedCategories['categories'],
            'parentCategories' => $categorizedCategories['parentCategories'],
            'childCategories'  => $categorizedCategories['childCategories'],
            'stats'            => $stats,
        ]);
    }

    private function getQuestionListUrl(Category $category): string
    {
        return $this->adminUrlGenerator
            ->setController(QuestionCrudController::class)
            ->setAction('index')
            ->set('filters[category][comparison]', '=')
            ->set('filters[category][value]', $category->getId())
            ->generateUrl();
    }
}
