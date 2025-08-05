<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Difficulty;
use App\Repository\DifficultyRepository;
use Doctrine\ORM\EntityManagerInterface;

class DifficultyService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DifficultyRepository $difficultyRepository,
    ) {
    }

    public function restore(int $difficultyId): void
    {
        // Désactiver temporairement le filtre SoftDeleteable
        $this->entityManager->getFilters()->disable('softdeleteable');
        // Récupérer la catégorie
        $difficulty = $this->difficultyRepository->find($difficultyId);

        if (!$difficulty instanceof Difficulty) {
            throw new \InvalidArgumentException('Difficulté non trouvée');
        }

        if (null === $difficulty->getDeletedAt()) {
            throw new \LogicException('Cette catégorie n\'est pas supprimée');
        }

        $difficulty->setDeletedAt(null);
        $this->entityManager->persist($difficulty);
        $this->entityManager->flush();

        // Réactiver le filtre
        $this->entityManager->getFilters()->enable('softdeleteable');
    }

    public function duplicate(int $difficultyId): Difficulty
    {
        $difficulty = $this->difficultyRepository->find($difficultyId);

        if (!$difficulty instanceof Difficulty) {
            throw new \InvalidArgumentException('Difficulté non trouvée');
        }

        $duplicate = new Difficulty();
        $duplicate->setName($difficulty->getName() . ' (Copie)');
        $duplicate->setLevel($difficulty->getLevel());

        $this->entityManager->persist($duplicate);
        $this->entityManager->flush();

        return $duplicate;
    }
}
