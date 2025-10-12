<?php

declare(strict_types=1);

namespace App\Quiz\Service\Import;

use App\DTO\ImportSummaryDto;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class QuizImporterService
{
    public const string DEFAULT_LOCALE = 'fr';

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
     * Reads the content of a JSON file.
     */
    public function readJsonFile(UploadedFile $jsonFile): string|false
    {
        return file_get_contents($jsonFile->getPathname());
    }

    /**
     * Imports quiz data from a JSON string.
     */
    public function importFromJson(string $jsonContent, string $defaultLocale = self::DEFAULT_LOCALE): ImportSummaryDto
    {
        $importSummary = new ImportSummaryDto();
        $data          = json_decode($jsonContent, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            $this->logger->error('Invalid JSON provided: ' . json_last_error_msg());
            ++$importSummary->errors;
            $importSummary->errorMessages[] = 'Invalid JSON: ' . json_last_error_msg();

            return $importSummary;
        }

        $this->entityManager->beginTransaction();

        try {
            $this->processQuizData($data, $defaultLocale, $importSummary);
            $this->entityManager->flush(); // Flush toutes les entités persistées
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Error during import: ' . $e->getMessage(), ['exception' => $e]);
            ++$importSummary->errors;
            $importSummary->errorMessages[] = 'Import failed: ' . $e->getMessage();
        }

        return $importSummary;
    }

    /**
     * Processes the quiz data structure and delegates to specialized services.
     *
     * @param array<string, mixed> $data
     */
    private function processQuizData(array $data, string $defaultLocale, ImportSummaryDto $importSummary): void
    {
        $categoryTranslations = $data['catégorie-nom-slogan'] ?? [];
        if (empty($categoryTranslations[$defaultLocale])) {
            throw new \RuntimeException("Default locale '$defaultLocale' data not found for category.");
        }

        $mainCategory = $this->categoryImportService->processMainCategory(
            $categoryTranslations,
            $defaultLocale,
            $importSummary
        );

        $subCategory = $this->categoryImportService->processSubCategory(
            $categoryTranslations,
            $defaultLocale,
            $mainCategory,
            $importSummary
        );

        $baseDifficultyLevel = (int) explode(' / ', $data['difficulté'])[0];
        $structuredQuestions = $this->quizStructureService->structureQuestionsByLevelAndLocale($data['quizz']);

        $getDifficultyEntity = fn ($base, $level) => $this->difficultyImportService
            ->getDifficultyEntity($base, $level, $importSummary);

        $this->questionImportService->processQuestions(
            $structuredQuestions,
            $subCategory,
            $baseDifficultyLevel,
            $defaultLocale,
            $importSummary,
            $getDifficultyEntity
        );
    }
}
