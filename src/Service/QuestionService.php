<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Proposal;
use App\Entity\Question;
use App\Repository\CategoryRepository;
use App\Repository\ProposalRepository;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class QuestionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuestionRepository $questionRepository,
        private CategoryRepository $categoryRepository,
        private ProposalRepository $proposalRepository,
    ) {
    }

    public function getQuestionById(int $id): ?Question
    {
        return $this->questionRepository->find($id);
    }

    public function restore(int $questionId): void
    {
        // Désactiver temporairement le filtre SoftDeleteable
        $this->entityManager->getFilters()->disable('softdeleteable');
        // Récupérer la question
        $question = $this->questionRepository->find($questionId);

        if (!$question instanceof Question) {
            throw new \InvalidArgumentException('Question non trouvée');
        }

        if (null === $question->getDeletedAt()) {
            throw new \LogicException('Cette question n\'est pas supprimée');
        }

        $question->setDeletedAt(null);
        $this->entityManager->persist($question);

        foreach ($question->getProposals() as $proposal) {
            $proposal->setDeletedAt(null);
            $this->entityManager->persist($proposal);
        }

        $this->entityManager->flush();

        // Réactiver le filtre
        $this->entityManager->getFilters()->enable('softdeleteable');
    }

    public function duplicate(int $questionId): ?Question
    {
        // Récupérer la question
        $question = $this->questionRepository->find($questionId);

        if (!$question instanceof Question) {
            throw new \InvalidArgumentException('Question non trouvée');
        }

        $duplicate = new Question();
        $duplicate->setContent($question->getContent() . ' (Copie)');
        $duplicate->setExplanation($question->getExplanation());
        $duplicate->setHint($question->getHint());
        $duplicate->setCategory($question->getCategory());
        $duplicate->setDifficulty($question->getDifficulty());

        foreach ($question->getProposals() as $proposal) {
            $newProposal = new Proposal();
            $newProposal->setContent($proposal->getContent());
            $newProposal->setIsCorrect($proposal->isCorrect());
            $duplicate->addProposal($newProposal);
        }

        $this->entityManager->persist($duplicate);
        $this->entityManager->flush();

        return $duplicate;
    }

    /**
     * Fusionne les résultats de questions invalides (mauvaises propositions et mauvaises réponses correctes)
     * en un seul tableau, en regroupant les problèmes par question.
     *
     * @param array<int, array{
     *     id: int,
     *     content: string,
     *     categoryName: string|null,
     *     proposalsCount: int|string,
     *     correctCount: int|string}
     *     > $wrongProposal
     * @param array<int, array{
     *     id: int,
     *     content: string,
     *     categoryName: string|null,
     *     proposalsCount: int|string,
     *     correctCount: int|string}
     *     > $wrongCorrect
     *
     * @return array<int, array{
     *     id: int,
     *     content: string,
     *     category: string|null,
     *     issues: string[],
     *     proposalsCount: int,
     *     correctCount: int
     * }>
     */
    private function mergeInvalidQuestions(array $wrongProposal, array $wrongCorrect): array
    {
        $invalidQuestions = [];
        $processedIds     = [];

        foreach ($wrongProposal as $result) {
            $invalidQuestions[] = $this->createInvalidQuestion(
                $result,
                [sprintf('A %d propositions au lieu de 4', $result['proposalsCount'])]
            );

            $processedIds[] = $result['id'];
        }

        foreach ($wrongCorrect as $result) {
            if (in_array($result['id'], $processedIds, true)) {
                $this->addIssueToExisting($invalidQuestions, $result);
                continue;
            }

            $invalidQuestions[] = $this->createInvalidQuestion(
                $result,
                [sprintf('A %d bonne(s) réponse(s) au lieu de 1', $result['correctCount'])]
            );
        }

        return $invalidQuestions;
    }

    /**
     * Crée un tableau représentant une question invalide avec ses informations et ses problèmes.
     *
     * @param array{
     *     id: int,
     *     content: string,
     *     categoryName: string|null,
     *     proposalsCount: int|string,
     *     correctCount: int|string
     * } $result
     * @param string[] $issues
     *
     * @return array{
     *     id: int,
     *     content: string,
     *     category: string|null,
     *     issues: string[],
     *     proposalsCount: int,
     *     correctCount: int
     * }
     */
    private function createInvalidQuestion(array $result, array $issues): array
    {
        return [
            'id'             => $result['id'],
            'content'        => $result['content'],
            'category'       => $result['categoryName'],
            'issues'         => $issues,
            'proposalsCount' => (int) $result['proposalsCount'],
            'correctCount'   => (int) $result['correctCount'],
        ];
    }

    /**
     * Ajoute un problème supplémentaire à une question invalide déjà existante
     * dans la liste des questions invalides, en mettant à jour également les
     * compteurs de bonnes réponses et de propositions si nécessaire.
     *
     * @param array<int, array{
     *     id: int,
     *     content: string,
     *     category: string|null,
     *     issues: string[],
     *     proposalsCount: int,
     *     correctCount: int
     * }> $invalidQuestions Tableau passé par référence contenant les questions invalides
     * @param array{
     *     id: int,
     *     content: string,
     *     categoryName: string|null,
     *     proposalsCount: int|string,
     *     correctCount: int|string
     * } $result Données de la question à fusionner avec une question déjà présente
     */
    private function addIssueToExisting(array &$invalidQuestions, array $result): void
    {
        foreach ($invalidQuestions as &$question) {
            if ($question['id'] === $result['id']) {
                $question['issues'][] = sprintf(
                    'A %d bonne(s) réponse(s) au lieu de 1',
                    $result['correctCount']
                );
                $question['correctCount'] = (int) $result['correctCount'];

                if (0 === $question['proposalsCount']) {
                    $question['proposalsCount'] = (int) $result['proposalsCount'];
                }
                break;
            }
        }
    }

    /**
     * Récupère diverses statistiques globales sur les questions et propositions
     * (totaux, par catégorie, par difficulté, etc.).
     *
     * @return array{
     *     questions: array{
     *         total: int,
     *         active: int,
     *         with_proposals: int,
     *         without_proposals: int,
     *         incomplete: int
     *     },
     *     proposals: array{
     *         total: int,
     *         correct: int
     *     },
     *     by_category: array<int, array{category: string|null, count: string}>,
     *     by_difficulty: array<int, array{difficulty: string|null, count: string}>
     * }
     */
    public function getDataForStats(): array
    {
        $this->entityManager->getFilters()->disable('softdeleteable');

        $stats = [
            'questions' => [
                'total'             => $this->questionRepository->countAll(),
                'active'            => $this->questionRepository->countActive(),
                'with_proposals'    => $this->questionRepository->countWithProposals(),
                'without_proposals' => $this->questionRepository->countWithoutProposals(),
                'incomplete'        => $this->questionRepository->countQuestionsForProposalCountNotEqualTo(4),
            ],
            'proposals' => [
                'total'   => $this->proposalRepository->countAllActive(),
                'correct' => $this->proposalRepository->countCorrect(),
            ],
            'by_category'   => $this->questionRepository->countByCategory(),
            'by_difficulty' => $this->questionRepository->countByDifficulty(),
        ];

        $this->entityManager->getFilters()->enable('softdeleteable');

        return $stats;
    }

    /**
     * Récupère les données nécessaires pour le rapport de nettoyage des questions
     * (questions invalides, nombre de questions valides, total).
     *
     * @return array{
     *     invalidQuestions: array<int, array{
     *         id: int,
     *         content: string,
     *         category: string|null,
     *         issues: string[],
     *         proposalsCount: int,
     *         correctCount: int
     *     }>,
     *     validCount: int,
     *     totalCount: int
     * }
     */
    public function getDataForSanitizeReport(): array
    {
        $this->entityManager->getFilters()->disable('softdeleteable');

        $wrongProposal = $this->questionRepository->findWithWrongProposalCount();
        $wrongCorrect  = $this->questionRepository->findWithWrongCorrectCount();
        $validCount    = $this->questionRepository->countValidQuestions();
        $totalCount    = $this->questionRepository->countActive();

        $this->entityManager->getFilters()->enable('softdeleteable');

        $invalidQuestions = $this->mergeInvalidQuestions($wrongProposal, $wrongCorrect);

        return [
            'invalidQuestions' => $invalidQuestions,
            'validCount'       => $validCount,
            'totalCount'       => $totalCount,
        ];
    }

    /**
     * Récupère les données des questions pour une catégorie donnée, formatées pour l'API.
     *
     * @param int $categoryId L'ID de la catégorie
     *
     * @return array{
     *     questions: array<int, array{
     *         id: int,
     *         content: string,
     *         difficulty: string|null,
     *         created_at: string
     *     }>,
     *     count: int
     * }|null
     */
    public function getQuestionsDataForApi(int $categoryId): ?array
    {
        $category = $this->categoryRepository->find($categoryId);
        if (!$category) {
            return null;
        }

        $questions = $this->questionRepository->findBy([
            'category'  => $category,
            'deletedAt' => null,
        ]);

        $questionsData = array_map(fn ($q) => [
            'id'         => $q->getId(),
            'content'    => substr($q->getContent(), 0, 80) . (strlen($q->getContent()) > 80 ? '...' : ''),
            'difficulty' => $q->getDifficulty()?->getName(),
            'created_at' => $q->getCreatedAt()->format('d/m/Y H:i'),
        ], $questions);

        return [
            'questions' => $questionsData,
            'count'     => count($questions),
        ];
    }
}
