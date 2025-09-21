<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Difficulty;
use App\Quiz\Exception\QuizNotFoundException;
use App\Repository\DifficultyRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class DifficultyService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DifficultyRepository $difficultyRepository,
    ) {
    }

    public function duplicate(int $difficultyId): Difficulty
    {
        $difficulty = $this->difficultyRepository->find($difficultyId);

        if (!$difficulty instanceof Difficulty) {
            throw new QuizNotFoundException('Difficulté non trouvée');
        }

        $duplicate = new Difficulty();
        $duplicate->setName($difficulty->getName() . ' (Copie)');
        $duplicate->setLevel($difficulty->getLevel());

        $this->entityManager->persist($duplicate);
        $this->entityManager->flush();

        return $duplicate;
    }
}
