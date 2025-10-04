<?php

declare(strict_types=1);

namespace App\Quiz\Service\Import;

use App\Entity\Difficulty;
use App\Repository\DifficultyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

readonly class DifficultyImportService
{
    public function __construct(
        private DifficultyRepository $difficultyRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    // Ce service gérera la création et le calcul des difficultés

    /**
     * Returns a Difficulty entity for the given base level and level name, creating it if necessary.
     *
     * @param array{
     *   categories_created: int,
     *   categories_updated: int,
     *   questions_created: int,
     *   proposals_created: int,
     *   difficulties_created: int,
     *   errors: int,
     *   error_messages: string[]
     * } &$importStats
     */
    public function getDifficultyEntity(int $baseDifficultyLevel, string $levelName, array &$importStats): ?Difficulty
    {
        $level = $this->calculateDifficultyLevel($baseDifficultyLevel, $levelName, $importStats);
        if (null === $level) {
            return null;
        }
        if ($level < 1 || $level > 5) {
            $this->logger->warning(
                sprintf('Calculated difficulty level out of bounds (1-5): %d for %s', $level, $levelName)
            );
            $importStats['error_messages'][] = sprintf(
                'Calculated difficulty level out of bounds (1-5): %d for %s',
                $level,
                $levelName
            );
            ++$importStats['errors'];

            return null;
        }
        $difficulty = $this->difficultyRepository->findOneBy(['level' => $level]);
        if (!$difficulty) {
            $difficulty = new Difficulty();
            $difficulty->setLevel($level);
            $difficulty->setName('Niveau ' . $level);
            $this->entityManager->persist($difficulty);
            ++$importStats['difficulties_created'];
        }

        return $difficulty;
    }

    /**
     * Calculates the difficulty level based on the base level and level name.
     *
     * @param array{
     *   categories_created: int,
     *   categories_updated: int,
     *   questions_created: int,
     *   proposals_created: int,
     *   difficulties_created: int,
     *   errors: int,
     *   error_messages: string[]
     * } &$importStats
     */
    public function calculateDifficultyLevel(int $baseDifficultyLevel, string $levelName, array &$importStats): ?int
    {
        $level = 0;
        switch (strtolower($levelName)) {
            case 'débutant':
                $level = max(1, $baseDifficultyLevel - 1);
                break;
            case 'confirmé':
                $level = $baseDifficultyLevel;
                break;
            case 'expert':
                $level = min(5, $baseDifficultyLevel + 1);
                break;
            default:
                $this->logger->warning("Unknown difficulty level name: {$levelName}");
                $importStats['error_messages'][] = "Unknown difficulty level name: {$levelName}";
                ++$importStats['errors'];

                return null;
        }

        return $level;
    }
}
