<?php

declare(strict_types=1);

namespace App\Quiz\Service\Import;

use App\DTO\ImportSummaryDto;
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
     */
    public function getDifficultyEntity(
        int $baseDifficultyLevel,
        string $levelName,
        ImportSummaryDto $importSummary,
    ): ?Difficulty {
        $level = $this->calculateDifficultyLevel($baseDifficultyLevel, $levelName, $importSummary);
        if (null === $level) {
            return null;
        }
        if ($level < 1 || $level > 5) {
            $this->logger->warning(
                sprintf('Calculated difficulty level out of bounds (1-5): %d for %s', $level, $levelName)
            );
            $importSummary->errorMessages[] = sprintf(
                'Calculated difficulty level out of bounds (1-5): %d for %s',
                $level,
                $levelName
            );
            ++$importSummary->errors;

            return null;
        }
        $difficulty = $this->difficultyRepository->findOneBy(['level' => $level]);
        if (!$difficulty) {
            $difficulty = new Difficulty();
            $difficulty->setLevel($level);
            $difficulty->setName('Niveau ' . $level);
            $this->entityManager->persist($difficulty);
            ++$importSummary->difficultiesCreated;
        }

        return $difficulty;
    }

    /**
     * Calculates the difficulty level based on the base level and level name.
     */
    public function calculateDifficultyLevel(
        int $baseDifficultyLevel,
        string $levelName,
        ImportSummaryDto $importSummary,
    ): ?int {
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
                $importSummary->errorMessages[] = "Unknown difficulty level name: {$levelName}";
                ++$importSummary->errors;

                return null;
        }

        return $level;
    }
}
