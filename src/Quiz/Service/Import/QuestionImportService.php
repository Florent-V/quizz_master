<?php

declare(strict_types=1);

namespace App\Quiz\Service\Import;

use App\DTO\ImportSummaryDto;
use App\Entity\Category;
use App\Entity\Difficulty;
use App\Entity\Proposal;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\TranslatableListener;
use Psr\Log\LoggerInterface;

readonly class QuestionImportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslatableListener $translatableListener,
        private LoggerInterface $logger,
    ) {
    }

    // Ce service gérera la création, la traduction et la persistance des questions et propositions

    /**
     * Processes all questions and their proposals for all levels and locales.
     *
     * @param array<string, array<string, array<string, array{
     *     question: string,
     *     propositions: array<int, string>,
     *     réponse: string,
     *     anecdote?: string
     * }>>> $structuredQuestions
     */
    public function processQuestions(
        array $structuredQuestions,
        Category $subCategory,
        int $baseDifficultyLevel,
        string $defaultLocale,
        ImportSummaryDto $importSummary,
        callable $getDifficultyEntity,
    ): void {
        foreach ($structuredQuestions as $levelName => $questionsByLevel) {
            $difficultyEntity = $getDifficultyEntity($baseDifficultyLevel, $levelName, $importSummary);
            if (!$difficultyEntity) {
                continue;
            }
            foreach ($questionsByLevel as $questionId => $questionLocalesData) {
                if (
                    !$this->hasDefaultLocale(
                        $questionLocalesData,
                        $defaultLocale,
                        $questionId,
                        $levelName,
                        $importSummary
                    )
                ) {
                    continue;
                }
                $questionItemDefault        = $questionLocalesData[$defaultLocale];
                list($question, $proposals) = $this->createAndPersistQuestion(
                    $questionItemDefault,
                    $subCategory,
                    $difficultyEntity,
                    $defaultLocale,
                    $importSummary
                );
                $this->handleTranslations($question, $proposals, $questionLocalesData, $defaultLocale);
            }
        }
    }

    /**
     * Checks if the default locale exists in the question data and logs an error if missing.
     *
     * @param array<string, array{
     *     question: string,
     *     propositions: array<int, string>, réponse: string, anecdote?: string
     * }>  $questionLocalesData
     */
    private function hasDefaultLocale(
        array $questionLocalesData,
        string $defaultLocale,
        int|string $questionId,
        string $levelName,
        ImportSummaryDto $importSummary,
    ): bool {
        if (!isset($questionLocalesData[$defaultLocale])) {
            $this->logger->warning(
                sprintf(
                    'Default locale data not found for question ID %d in level %s.',
                    $questionId,
                    $levelName
                )
            );
            ++$importSummary->errors;
            $importSummary->errorMessages[] = sprintf(
                'Default locale data missing for question ID %d in level %s.',
                $questionId,
                $levelName
            );

            return false;
        }

        return true;
    }

    /**
     * Creates and persists the question and its proposals for the default locale.
     *
     * @param array{
     *     question: string,
     *     propositions: array<int, string>,
     *     réponse: string,
     *     anecdote?: string
     * } $questionItemDefault
     *
     * @return array{0: Question, 1: array<int, Proposal>}
     */
    private function createAndPersistQuestion(
        array $questionItemDefault,
        Category $subCategory,
        Difficulty $difficultyEntity,
        string $defaultLocale,
        ImportSummaryDto $importSummary,
    ): array {
        list('question' => $question, 'proposals' => $proposals) = $this->createBaseQuestionAndProposals(
            $questionItemDefault,
            $subCategory,
            $difficultyEntity,
            $defaultLocale,
            $importSummary
        );
        $this->entityManager->persist($question);
        $this->translatableListener->setTranslatableLocale($defaultLocale);
        $this->entityManager->flush();

        return [$question, $proposals];
    }

    /**
     * Handles translations for the question and its proposals for all non-default locales.
     *
     * @param array<int, Proposal> $proposals
     * @param array<string, array{
     *     question: string,
     *     propositions: array<int, string>,
     *     réponse: string, anecdote?: string
     * }>  $questionLocalesData
     */
    private function handleTranslations(
        Question $question,
        array $proposals,
        array $questionLocalesData,
        string $defaultLocale,
    ): void {
        foreach ($questionLocalesData as $locale => $questionItem) {
            if ($locale === $defaultLocale) {
                continue;
            }
            $this->updateQuestionAndProposalsTranslations($question, $proposals, $questionItem, $locale);
            $this->translatableListener->setTranslatableLocale($locale);
            $this->entityManager->flush();
        }
    }

    /**
     * Creates the base question and its proposals for a given locale.
     *
     * @param array{
     *     question: string,
     *     propositions: array<int, string>, réponse: string, anecdote?: string
     * }  $questionItem
     *
     * @return array{question: Question, proposals: array<int, Proposal>}
     */
    private function createBaseQuestionAndProposals(
        array $questionItem,
        Category $subCategory,
        Difficulty $difficultyEntity,
        string $defaultLocale,
        ImportSummaryDto $importSummary,
    ): array {
        $question = new Question();
        $question->setCategory($subCategory);
        $question->setDifficulty($difficultyEntity);
        $question->setTranslatableLocale($defaultLocale);
        $question->setContent($questionItem['question']);
        if (isset($questionItem['anecdote'])) {
            $question->setExplanation($questionItem['anecdote']);
        }
        $this->entityManager->persist($question);
        ++$importSummary->questionsCreated;
        $proposals = [];
        foreach ($questionItem['propositions'] as $propContent) {
            $isCorrect = ($propContent === $questionItem['réponse']);
            $proposal  = new Proposal();
            $proposal->setQuestion($question);
            $proposal->setIsCorrect($isCorrect);
            $proposal->setTranslatableLocale($defaultLocale);
            $proposal->setContent($propContent);
            $this->entityManager->persist($proposal);
            $proposals[] = $proposal;
            ++$importSummary->proposalsCreated;
        }

        return ['question' => $question, 'proposals' => $proposals];
    }

    /**
     * Updates translations for the question and its proposals for a given locale.
     *
     * @param array<int, Proposal> $proposals
     * @param array{
     *     question: string,
     *     propositions: array<int, string>,
     *     réponse: string, anecdote?: string
     * }  $questionItem
     */
    private function updateQuestionAndProposalsTranslations(
        Question $question,
        array $proposals,
        array $questionItem,
        string $locale,
    ): void {
        $question->setTranslatableLocale($locale);
        $question->setContent($questionItem['question']);
        if (isset($questionItem['anecdote'])) {
            $question->setExplanation($questionItem['anecdote']);
        }
        $this->entityManager->persist($question);
        foreach ($questionItem['propositions'] as $propIndex => $propContent) {
            if (isset($proposals[$propIndex])) {
                $proposals[$propIndex]->setTranslatableLocale($locale);
                $proposals[$propIndex]->setContent($propContent);
                $this->entityManager->persist($proposals[$propIndex]);
            }
        }
    }
}
