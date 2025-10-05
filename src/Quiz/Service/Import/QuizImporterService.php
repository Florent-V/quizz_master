<?php

declare(strict_types=1);

namespace App\Quiz\Service\Import;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class QuizImporterService
{
    public const string DEFAULT_LOCALE = 'fr';

    // Compteurs pour le résumé
    /** @var array<string, int|string[]> */
    public array $importStats = [
        'categories_created'   => 0,
        'categories_updated'   => 0,
        'questions_created'    => 0,
        'proposals_created'    => 0,
        'difficulties_created' => 0,
        'errors'               => 0,
        'error_messages'       => [],
    ];

    public function __construct(
        private readonly CategoryImportService $categoryImportService,
        private readonly DifficultyImportService $difficultyImportService,
        private readonly QuizStructureService $quizStructureService,
        private readonly QuestionImportService $questionImportService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Resets the import statistics.
     */
    public function resetStats(): void
    {
        $this->importStats = [
            'categories_created'   => 0,
            'categories_updated'   => 0,
            'questions_created'    => 0,
            'proposals_created'    => 0,
            'difficulties_created' => 0,
            'errors'               => 0,
            'error_messages'       => [],
        ];
    }

    /**
     * Reads the content of a JSON file.
     */
    public function readJsonFile(UploadedFile $jsonFile): string|false
    {
        return file_get_contents($jsonFile->getPathname());
    }

    /**
     * Imports quiz data from a JSON string.
     *
     * @return array<string, int|string[]>
     */
    public function importFromJson(string $jsonContent, string $defaultLocale = self::DEFAULT_LOCALE): array
    {
        $this->resetStats();
        $data = json_decode($jsonContent, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            $this->logger->error('Invalid JSON provided: ' . json_last_error_msg());
            ++$this->importStats['errors'];
            $this->importStats['error_messages'][] = 'Invalid JSON: ' . json_last_error_msg();

            return $this->importStats;
        }

        $this->entityManager->beginTransaction();

        try {
            $this->processQuizData($data, $defaultLocale);
            $this->entityManager->flush(); // Flush toutes les entités persistées
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Error during import: ' . $e->getMessage(), ['exception' => $e]);
            ++$this->importStats['errors'];
            $this->importStats['error_messages'][] = 'Import failed: ' . $e->getMessage();
        }

        return $this->importStats;
    }

    /**
     * Processes the quiz data structure and delegates to specialized services.
     *
     * @param array<string, mixed> $data
     */
    private function processQuizData(array $data, string $defaultLocale): void
    {
        $categoryTranslations = $data['catégorie-nom-slogan'] ?? [];
        if (empty($categoryTranslations[$defaultLocale])) {
            throw new \RuntimeException("Default locale '$defaultLocale' data not found for category.");
        }

        $mainCategory = $this->categoryImportService->processMainCategory(
            $categoryTranslations,
            $defaultLocale
        );
        $subCategory = $this->categoryImportService->processSubCategory(
            $categoryTranslations,
            $defaultLocale,
            $mainCategory
        );

        $baseDifficultyLevel = (int) explode(' / ', $data['difficulté'])[0];
        $structuredQuestions = $this->quizStructureService->structureQuestionsByLevelAndLocale($data['quizz']);

        $getDifficultyEntity = fn ($base, $level, &$stats) => $this->difficultyImportService->getDifficultyEntity(
            $base,
            $level,
            $stats
        );
        $this->questionImportService->processQuestions(
            $structuredQuestions,
            $subCategory,
            $baseDifficultyLevel,
            $defaultLocale,
            $this->importStats,
            $getDifficultyEntity
        );
    }
}
