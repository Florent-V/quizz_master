<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Category;
use App\Service\CategoryService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CategoryExtension extends AbstractExtension
{
    public function __construct(private readonly CategoryService $categoryService)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_total_questions_count', [$this, 'getTotalQuestionsCount']),
        ];
    }

    public function getTotalQuestionsCount(Category $category): int
    {
        return $this->categoryService->getTotalQuestionsCount($category);
    }
}
