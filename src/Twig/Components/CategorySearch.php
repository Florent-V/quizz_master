<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Repository\CategoryRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class CategorySearch
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    public function __construct(private readonly CategoryRepository $categoryRepository)
    {
    }

    public function getCategories(): array
    {
        // example method that returns an array of Products
        return $this->categoryRepository->search($this->query);
    }
}
