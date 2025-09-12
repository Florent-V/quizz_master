<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\AIQuizDTO;
use App\Entity\Category;
use App\Entity\Proposal;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @phpstan-type TProposal array{content: string, isCorrect: bool}
 * @phpstan-type TProposals array<int, TProposal>
 * @phpstan-type TQuestion array{content: string, explanation: ?string, hint: ?string, proposals: TProposals}
 * @phpstan-type TQuiz array{
 *     category: string,
 *     subCategory: string,
 *     difficulty: string,
 *     questions: array<int,
 *     TQuestion>
 *     }
 */
readonly class AIQuizImportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryService $categoryService,
    ) {
    }

    /**
     * @param TQuiz $quizData
     *
     * @return array{category: Category, subCategory: Category, questions: Question[]}
     */
    public function persistQuestions(array $quizData, AIQuizDTO $dto): array
    {
        $parentCategory = $this->categoryService->getOrCreateCategory($quizData['category']);
        $subCategory    = $this->categoryService->getOrCreateCategory($quizData['subCategory'], $parentCategory);

        $createdQuestions = [];

        foreach ($quizData['questions'] as $questionData) {
            if (!$this->isQuestionDataValid($questionData)) {
                continue; // Ignorer les questions mal formées
            }

            $question = $this->createQuestion($questionData, $dto, $subCategory);
            $this->addProposalsToQuestion($question, $questionData['proposals']);

            $this->entityManager->persist($question);
            $createdQuestions[] = $question;
        }

        $this->entityManager->flush();

        return [
            'category'    => $parentCategory,
            'subCategory' => $subCategory,
            'questions'   => $createdQuestions,
        ];
    }

    /**
     * @param TQuestion $questionData
     */
    private function isQuestionDataValid(array $questionData): bool
    {
        return !empty($questionData['proposals']) && 4 === count($questionData['proposals']);
    }

    /**
     * @param TQuestion $questionData
     */
    private function createQuestion(array $questionData, AIQuizDTO $dto, Category $subCategory): Question
    {
        $question = new Question();
        $question->setContent($questionData['content']);
        $question->setExplanation($questionData['explanation'] ?? null);
        $question->setHint($questionData['hint'] ?? null);
        $question->setDifficulty($dto->difficulty);
        $question->setCategory($subCategory);

        return $question;
    }

    /**
     * @param TProposals $proposalsData
     */
    private function addProposalsToQuestion(Question $question, array $proposalsData): void
    {
        foreach ($proposalsData as $proposalData) {
            $proposal = new Proposal();
            $proposal->setContent($proposalData['content']);
            $proposal->setIsCorrect($proposalData['isCorrect']);
            $question->addProposal($proposal);
        }
    }
}
