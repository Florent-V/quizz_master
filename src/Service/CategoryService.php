<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

readonly class CategoryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository,
        private Filesystem $filesystem,
        private ParameterBagInterface $parameterBag,
    ) {
    }

    public function restore(int $categoryId): void
    {
        // Désactiver temporairement le filtre SoftDeleteable
        $this->entityManager->getFilters()->disable('softdeleteable');
        // Récupérer la catégorie
        $category = $this->categoryRepository->find($categoryId);

        if (!$category instanceof Category) {
            throw new \InvalidArgumentException('Catégorie non trouvée');
        }

        if (null === $category->getDeletedAt()) {
            throw new \LogicException('Cette catégorie n\'est pas supprimée');
        }

        $category->setDeletedAt(null);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        // Réactiver le filtre
        $this->entityManager->getFilters()->enable('softdeleteable');
    }

    public function duplicate(int $categoryId): Category
    {
        // Récupérer la catégorie
        $category = $this->categoryRepository->find($categoryId);

        if (!$category instanceof Category) {
            throw new \InvalidArgumentException('Catégorie non trouvée');
        }

        $duplicate = new Category();
        $duplicate->setName($category->getName() . ' (Copie)');
        $duplicate->setDescription($category->getDescription());
        $duplicate->setIcon($category->getIcon());
        $duplicate->setParent($category->getParent());

        // Copier l'image si elle existe
        if ($category->getImageName()) {
            $uploadsPath     = $this->parameterBag->get('kernel.project_dir') . '/public/uploads/images/categories/';
            $sourceImagePath = $uploadsPath . $category->getImageName();

            if ($this->filesystem->exists($sourceImagePath)) {
                // Crée un objet File à partir de l'image existante
                $file = new File($sourceImagePath);
                // Assigne le fichier à la nouvelle entité. VichUploader s'occupera de la copie.
                $duplicate->setImageFile($file);
                // Le imageName sera défini par VichUploader après le flush
                // $duplicate->setImageName($category->getImageName()); // Ne pas définir directement
            }
        }

        $this->entityManager->persist($duplicate);
        $this->entityManager->flush();

        return $duplicate;
    }
}
