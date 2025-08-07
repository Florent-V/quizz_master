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
