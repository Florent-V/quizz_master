<?php

declare(strict_types=1);

namespace App\Quiz\Service\Import;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\String\Slugger\SluggerInterface;

readonly class CategoryImportService
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private TranslatableListener $translatableListener,
    ) {
    }

    // Ce service gérera la création et la traduction des catégories

    /**
     * Creates or updates the main category and its translations.
     *
     * @param array<string, array{catégorie?: string, slogan?: string, nom?: string}>  $categoryTranslations
     */
    public function processMainCategory(array $categoryTranslations, string $defaultLocale): Category
    {
        $mainCategoryName = $categoryTranslations[$defaultLocale]['catégorie'];
        $mainCategory     = $this->findOrCreateCategory($mainCategoryName, null);
        foreach ($categoryTranslations as $locale => $translation) {
            $mainCategory->setTranslatableLocale($locale);
            if (isset($translation['catégorie'])) {
                $mainCategory->setName($translation['catégorie']);
            }
            if (isset($translation['slogan'])) {
                $mainCategory->setDescription($translation['slogan']);
            }
            $this->entityManager->persist($mainCategory);
            $this->translatableListener->setTranslatableLocale($locale);
            $this->entityManager->flush();
        }

        return $mainCategory;
    }

    /**
     * Creates or updates the subcategory and its translations.
     *
     * @param array<string, array{catégorie?: string, slogan?: string, nom?: string}>  $categoryTranslations
     */
    public function processSubCategory(
        array $categoryTranslations,
        string $defaultLocale,
        Category $mainCategory,
    ): Category {
        $subCategoryName = $categoryTranslations[$defaultLocale]['nom'];
        $subCategory     = $this->findOrCreateCategory($subCategoryName, $mainCategory);
        foreach ($categoryTranslations as $locale => $translation) {
            $subCategory->setTranslatableLocale($locale);
            if (isset($translation['nom'])) {
                $subCategory->setName($translation['nom']);
            }
            $this->entityManager->persist($subCategory);
            $this->translatableListener->setTranslatableLocale($locale);
            $this->entityManager->flush();
        }

        return $subCategory;
    }

    /**
     * Finds an existing category by slug and parent, or creates a new one.
     */
    public function findOrCreateCategory(string $name, ?Category $parent = null): Category
    {
        $slug     = (string) $this->slugger->slug($name)->lower();
        $criteria = ['slug' => $slug, 'parent' => $parent];
        $category = $this->categoryRepository->findOneBy($criteria);
        if ($category) {
            return $category;
        }
        $category = new Category();
        $category->setName($name);
        $category->setSlug($slug);
        if ($parent) {
            $category->setParent($parent);
        }
        $this->entityManager->persist($category);

        return $category;
    }
}
