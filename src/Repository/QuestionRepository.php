<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTO\HydratedQuizConfigurationDTO;
use App\Entity\Category;
use App\Entity\Difficulty;
use App\Entity\Question;
use App\Repository\Builder\QuestionQueryBuilder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Question>
 */
class QuestionRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly QuestionQueryBuilder $questionQueryBuilder,
        private readonly CategoryRepository $categoryRepository,
    ) {
        parent::__construct($registry, Question::class);
    }

    public function buildQueryBuilderForProposalCountNotEqualTo(int $count): QueryBuilder
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.proposals', 'p')
            ->where('q.deletedAt IS NULL')
            ->groupBy('q.id')
            ->having('COUNT(p.id) <> :count OR COUNT(p.id) IS NULL')
            ->setParameter('count', $count);
    }

    public function countQuestionsForProposalCountNotEqualTo(int $count): int
    {
        return (int) $this->createQueryBuilder('q')
            ->select('COUNT(DISTINCT q.id)')
            ->leftJoin('q.proposals', 'p')
            ->where('q.deletedAt IS NULL')
            ->groupBy('q.id')
            ->having('COUNT(p.id) <> :count')
            ->setParameter('count', $count)
            ->getQuery()
            ->getScalarResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Retourne le total des questions actives.
     */
    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.deletedAt IS NULL')
            ->andWhere('q.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countWithProposals(): int
    {
        return (int) $this->createQueryBuilder('q')
            ->select('COUNT(DISTINCT q.id)')
            ->leftJoin('q.proposals', 'p')
            ->where('q.deletedAt IS NULL')
            ->andWhere('p.id IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countWithoutProposals(): int
    {
        return (int) $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->leftJoin('q.proposals', 'p')
            ->where('q.deletedAt IS NULL')
            ->andWhere('p.id IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<int, array{category: string|null, count: string}>
     */
    public function countByCategory(): array
    {
        return $this->createQueryBuilder('q')
            ->select('c.name as category, COUNT(q.id) as count')
            ->leftJoin('q.category', 'c')
            ->where('q.deletedAt IS NULL')
            ->groupBy('c.id')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, array{difficulty: string|null, count: string}>
     */
    public function countByDifficulty(): array
    {
        return $this->createQueryBuilder('q')
            ->select('d.name as difficulty, COUNT(q.id) as count')
            ->leftJoin('q.difficulty', 'd')
            ->where('q.deletedAt IS NULL')
            ->groupBy('d.id')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les questions avec un nombre de propositions différent de 4.
     *
     * @return array<int, array{
     *      id: int,
     *      content: string,
     *      categoryName: string|null,
     *      proposalsCount: string,
     *      correctCount: string
     *  }>
     */
    public function findWithWrongProposalCount(): array
    {
        return $this->createQueryBuilder('q')
            ->select(
                'q.id',
                'q.content',
                'c.name as categoryName',
                'COUNT(p.id) as proposalsCount',
                '0 as correctCount'
            )
            ->leftJoin('q.category', 'c')
            ->leftJoin('q.proposals', 'p', 'WITH', 'p.deletedAt IS NULL')
            ->where('q.deletedAt IS NULL')
            ->groupBy('q.id', 'q.content', 'c.name')
            ->having('COUNT(p.id) != 4')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les questions avec un nombre de bonnes réponses différent de 1.
     *
     * @return array<int, array{
     *     id: int,
     *     content: string,
     *     categoryName: string|null,
     *     proposalsCount: string, // Doctrine retourne COUNT/SUM en string
     *     correctCount: string
     * }>
     */
    public function findWithWrongCorrectCount(): array
    {
        return $this->createQueryBuilder('q')
            ->select(
                'q.id',
                'q.content',
                'c.name as categoryName',
                'COUNT(p.id) as proposalsCount',
                'SUM(CASE WHEN p.isCorrect = 1 THEN 1 ELSE 0 END) as correctCount'
            )
            ->leftJoin('q.category', 'c')
            ->leftJoin('q.proposals', 'p', 'WITH', 'p.deletedAt IS NULL')
            ->where('q.deletedAt IS NULL')
            ->groupBy('q.id', 'q.content', 'c.name')
            ->having('SUM(CASE WHEN p.isCorrect = 1 THEN 1 ELSE 0 END) != 1')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne le nombre de questions valides (4 propositions et 1 seule bonne réponse).
     */
    public function countValidQuestions(): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<SQL
        SELECT COUNT(*) as total
        FROM (
            SELECT q.id
            FROM question q
            LEFT JOIN proposal p 
                ON p.question_id = q.id 
                AND p.deleted_at IS NULL
            WHERE q.deleted_at IS NULL
            GROUP BY q.id
            HAVING COUNT(p.id) = 4
               AND SUM(CASE WHEN p.is_correct = 1 THEN 1 ELSE 0 END) = 1
        ) as sub
    SQL;

        $result = $conn->fetchOne($sql);

        return (int) $result;
    }

    /**
     * Compte le nombre de questions disponibles pour chaque difficulté en fonction des filtres.
     *
     * @return array<int, int> [difficulty_id => count]
     */
    public function getAvailableDifficultyCounts(
        ?Category $category,
        ?Category $subCategory,
    ): array {
        $qb = $this->createQueryBuilder('q')
            ->select('d.id as difficulty_id, COUNT(q.id) as question_count')
            ->join('q.difficulty', 'd')
            ->join('q.category', 'c')
            ->where('q.deletedAt IS NULL')
            ->andWhere('q.isActive = :isActive')
            ->andWhere('c.isActive = :isActive')
            ->setParameter('isActive', true);

        if ($subCategory) {
            // Filtre par la sous-catégorie spécifique
            $qb->andWhere('q.category = :category')
                ->setParameter('category', $subCategory);
        } elseif ($category) {
            // Filtre par la catégorie parente et tous ses enfants
            $categoryIds = [$category->getId()];
            foreach ($category->getActiveChildren() as $child) {
                $categoryIds[] = $child->getId();
            }
            $qb->andWhere('q.category IN (:categoryIds)')
                ->setParameter('categoryIds', $categoryIds);
        }
        // Si aucune catégorie ou sous-catégorie n'est sélectionnée,
        // compte toutes les questions pour chaque difficulté

        $qb->groupBy('d.id');

        $results = $qb->getQuery()->getResult();

        // Formate le résultat en [difficulty_id => count]
        $counts = [];
        foreach ($results as $row) {
            $counts[(int) $row['difficulty_id']] = (int) $row['question_count'];
        }

        return $counts;
    }

    /**
     * Count number of question for each difficulty depending category and subcategory.
     *
     * @param int|null $categoryId    ID de la catégorie (optionnel)
     * @param int|null $subCategoryId ID de la sous-catégorie (optionnel)
     *
     * @return array<int, int> [difficulty_id => count]
     */
    public function getQuestionCountByDifficulty(
        ?int $categoryId,
        ?int $subCategoryId,
    ): array {
        $qb = $this->createQueryBuilder('q')
            ->select('d.id as difficulty_id, COUNT(q.id) as question_count')
            ->join('q.difficulty', 'd')
            ->join('q.category', 'c')
            ->where('q.deletedAt IS NULL')
            ->andWhere('q.isActive = :isActive')
            ->andWhere('c.isActive = :isActive')
            ->setParameter('isActive', true);

        if (null !== $subCategoryId) {
            // Filtre par la sous-catégorie spécifique
            $qb->andWhere('q.category = :categoryId')
                ->setParameter('categoryId', $subCategoryId);
        } elseif (null !== $categoryId) {
            // Filtre par la catégorie parente et tous ses enfants
            // Récupère les IDs des enfants actifs de la catégorie
            $categoryIds = [$categoryId];
            $children    = $this->categoryRepository->findActiveChildrenIds($categoryId);
            $categoryIds = array_merge($categoryIds, $children);

            $qb->andWhere('q.category IN (:categoryIds)')
                ->setParameter('categoryIds', $categoryIds);
        }

        // Si aucune catégorie ou sous-catégorie n'est sélectionnée,
        // compte toutes les questions pour chaque difficulté
        $qb->groupBy('d.id');

        $results = $qb->getQuery()->getResult();

        // Formate le résultat en [difficulty_id => count]
        $counts = [];
        foreach ($results as $row) {
            $counts[(int) $row['difficulty_id']] = (int) $row['question_count'];
        }

        return $counts;
    }

    /**
     * @return array<int, Question>
     */
    public function findQuestionsForQuiz(
        HydratedQuizConfigurationDTO $quizDto,
        ?int $limit,
    ): array {
        $qb = $this->createQueryBuilder('q')
            ->leftJoin('q.proposals', 'p')
            ->join('q.category', 'c')
            ->where('q.deletedAt IS NULL')
            ->andWhere('q.isActive = :isActive')
            ->andWhere('c.isActive = :isActive')
            ->setParameter('isActive', true);

        // Gérer la catégorie
        if ($quizDto->subCategory) {
            $qb->andWhere('q.category = :category')
                ->setParameter('category', $quizDto->subCategory);
        } elseif ($quizDto->category) {
            $categoryIds = [$quizDto->category->getId()];
            foreach ($quizDto->category->getActiveChildren() as $child) {
                $categoryIds[] = $child->getId();
            }
            $qb->andWhere('q.category IN (:categoryIds)')
                ->setParameter('categoryIds', $categoryIds);
        }

        // Gérer la difficulté
        if (!empty($quizDto->difficulties)) {
            $qb->andWhere('q.difficulty IN (:difficulties)')
                ->setParameter('difficulties', $quizDto->difficulties);
        }

        // S'assurer que la question est valide (4 propositions, 1 correcte)
        $qb->groupBy('q.id')
            ->having('COUNT(p.id) = 4 AND SUM(CASE WHEN p.isCorrect = 1 THEN 1 ELSE 0 END) = 1');

        // Ordonner aléatoirement et limiter le nombre de résultats
        $qb->addSelect('RAND() as HIDDEN rand')
            ->orderBy('rand');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, Question>
     */
    public function findRandomQuestionsForQuiz(
        ?int $limit,
    ): array {
        $qb = $this->createQueryBuilder('q')
            ->leftJoin('q.proposals', 'p')
            ->join('q.category', 'c')
            ->where('q.deletedAt IS NULL')
            ->andWhere('q.isActive = :isActive')
            ->andWhere('c.isActive = :isActive')
            ->setParameter('isActive', true);


        // S'assurer que la question est valide (4 propositions, 1 correcte)
        $qb->groupBy('q.id')
            ->having('COUNT(p.id) = 4 AND SUM(CASE WHEN p.isCorrect = 1 THEN 1 ELSE 0 END) = 1');

        // Ordonner aléatoirement et limiter le nombre de résultats
        $qb->addSelect('RAND() as HIDDEN rand')
            ->orderBy('rand');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param Difficulty[] $difficulties
     * @param int[]        $excludedQuestionIds
     *
     * @return array<int, Question>
     */
    public function findQuizSessionQuestions(
        ?Category $category,
        ?Category $subCategory,
        array $difficulties = [],
        int $limit = 1,
        array $excludedQuestionIds = [],
    ): array {
        return $this->questionQueryBuilder
            ->create()
            ->withCategory($category, $subCategory)
            ->withDifficulties($difficulties)
            ->excluding($excludedQuestionIds)
            ->onlyValid()
            ->randomized()
            ->limit($limit)
            ->getResult();
    }

    /**
     * Compte le nombre de questions disponibles pour une configuration donnée.
     */
    public function countQuestionsForQuiz(HydratedQuizConfigurationDTO $quizDto): int
    {
        $qb = $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->join('q.category', 'c')
            ->where('q.deletedAt IS NULL') // Exclure les questions supprimées
            ->andWhere('q.isActive = :isActive') // Exclure les questions inactives
            ->andWhere('c.isActive = :isActive') // Exclure les categories inactives
            ->setParameter('isActive', true);

        // Filtrage par catégorie
        if ($quizDto->category) {
            $qb->andWhere('q.category = :category')
                ->setParameter('category', $quizDto->category);
        }

        // Filtrage par sous-catégorie si définie
        if ($quizDto->subCategory) {
            $qb->andWhere('q.subCategory = :subCategory')
                ->setParameter('subCategory', $quizDto->subCategory);
        }

        // Filtrage par difficultés
        if (!empty($quizDto->difficulties)) {
            $difficultyIds = $quizDto->getDifficultyIds();
            $qb->andWhere('q.difficulty IN (:difficulties)')
                ->setParameter('difficulties', $difficultyIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findGoodAnswerId(int $questionId): int
    {
        return (int) $this->createQueryBuilder('q')
            ->select('p.id')
            ->leftJoin('q.proposals', 'p')
            ->where('q.id = :questionId')
            ->andWhere('p.isCorrect = true')
            ->setParameter('questionId', $questionId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Retrieves the hardest questions based on failure rate.
     * Only includes questions with at least 10 answers.
     *
     * @return array<int, array{
     *     0: object, // Question entity
     *     categoryName: string,
     *     totalAnswers: int,
     *     wrongAnswers: int,
     *     avgResponseTime: float,
     *     failureRate: float
     * }>
     */
    public function getHardestQuestions(int $limit = 20): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.quizSessionAnswers', 'a')
            ->leftJoin('q.category', 'c')
            ->select('
                q,
                c.name as categoryName,
                COUNT(a.id) as totalAnswers,
                SUM(CASE WHEN a.isCorrect = false THEN 1 ELSE 0 END) as wrongAnswers,
                AVG(a.time) as avgResponseTime,
                (SUM(CASE WHEN a.isCorrect = false THEN 1 ELSE 0 END) * 100.0 / COUNT(a.id)) as failureRate
            ')
            ->where('a.deletedAt IS NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->groupBy('q.id')
            ->having('COUNT(a.id) >= 10') // Au moins 10 réponses
            ->orderBy('failureRate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrieves the easiest questions based on success rate.
     * Only includes questions with at least 10 answers.
     *
     * @return array<int, array{
     *     0: object, // Question entity
     *     totalAnswers: int,
     *     correctAnswers: int,
     *     successRate: float
     * }>
     */
    public function getEasiestQuestions(int $limit = 20): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.quizSessionAnswers', 'a')
            ->select('q, COUNT(a.id) as totalAnswers,
                  COUNT(CASE WHEN a.isCorrect = true THEN 1 END) as correctAnswers,
                  (COUNT(CASE WHEN a.isCorrect = true THEN 1 END) * 100.0 / COUNT(a.id)) as successRate')
            ->where('a.deletedAt IS NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->groupBy('q.id')
            ->having('COUNT(a.id) >= 10')
            ->orderBy('successRate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrieves the most answered questions.
     *
     * @return array<int, array{
     *     0: object, // Question entity
     *     totalAnswers: int,
     *     successRate: float
     * }>
     */
    public function getMostAnsweredQuestions(int $limit = 20): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.quizSessionAnswers', 'a')
            ->select('q, COUNT(a.id) as totalAnswers,
                  AVG(CASE WHEN a.isCorrect = 1 THEN 1.0 ELSE 0.0 END) * 100 as successRate')
            ->where('a.deletedAt IS NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->groupBy('q.id')
            ->orderBy('totalAnswers', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrieves statistics grouped by question category.
     *
     * @return array<int, array{
     *     categoryName: string,
     *     totalQuestions: int,
     *     totalAnswers: int,
     *     successRate: float
     * }>
     */
    public function getStatsByCategory(): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.category', 'c')
            ->leftJoin('q.quizSessionAnswers', 'a')
            ->select('c.name as categoryName,
                  COUNT(DISTINCT q.id) as totalQuestions,
                  COUNT(a.id) as totalAnswers,
                  AVG(CASE WHEN a.isCorrect = 1 THEN 1.0 ELSE 0.0 END) * 100 as successRate')
            ->where('c.id IS NOT NULL')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->groupBy('c.id')
            ->orderBy('successRate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrieves questions with the slowest average response time (over 15 seconds).
     *
     * @return array<int, array{
     *     0: object, // Question entity
     *     avgTime: float
     * }>
     */
    public function getQuestionsWithSlowResponses(): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.quizSessionAnswers', 'a')
            ->select('q, AVG(a.time) as avgTime')
            ->where('a.time IS NOT NULL')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->groupBy('q.id')
            ->having('AVG(a.time) > 15000') // Plus de 15 secondes en moyenne
            ->orderBy('avgTime', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrieves categories with unbalanced success rates (below 30% or above 90%).
     *
     * @return array<int, array{
     *     categoryName: string,
     *     successRate: float
     * }>
     */
    public function getUnbalancedCategories(): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.category', 'c')
            ->leftJoin('q.quizSessionAnswers', 'a')
            ->select('c.name as categoryName,
                  AVG(CASE WHEN a.isCorrect = 1 THEN 1.0 ELSE 0.0 END) * 100 as successRate')
            ->where('c.id IS NOT NULL')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->groupBy('c.id')
            ->having('AVG(CASE WHEN a.isCorrect = 1 THEN 1.0 ELSE 0.0 END) * 100 < 30 
                  OR AVG(CASE WHEN a.isCorrect = 1 THEN 1.0 ELSE 0.0 END) * 100 > 90')
            ->orderBy('successRate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts questions without a category.
     */
    public function getQuestionsWithoutCategory(): int
    {
        return $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.category IS NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * get failure rate by category.
     *
     * @return array<string, array{rate: float, total: int, failures: int}>
     */
    public function getCategoryFailureRates(): array
    {
        $qb = $this->createQueryBuilder('q')
            ->select('
            c.name as categoryName,
            COUNT(qsa.id) as totalAnswers,
            SUM(CASE WHEN qsa.isCorrect = false OR qsa.isCorrect IS NULL THEN 1 ELSE 0 END)
            as wrongAnswers,
            (SUM(CASE WHEN qsa.isCorrect = false OR qsa.isCorrect IS NULL THEN 1 ELSE 0 END) * 100.0 / COUNT(qsa.id))
            as failureRate
        ')
            ->leftJoin('q.category', 'c')
            ->leftJoin('q.quizSessionAnswers', 'qsa')
            ->where('qsa.id IS NOT NULL') // Seulement les questions qui ont été répondues
            ->groupBy('c.id', 'c.name')
            ->having('COUNT(qsa.id) >= 10') // Minimum 10 réponses pour être significatif
            ->orderBy('failureRate', 'DESC');

        $results = $qb->getQuery()->getResult();

        $categoryFailures = [];

        foreach ($results as $result) {
            $totalAnswers = (int) $result['totalAnswers'];
            $wrongAnswers = (int) $result['wrongAnswers'];
            $failureRate  = (float) $result['failureRate'];

            $categoryFailures[$result['categoryName']] = [
                'rate'     => round($failureRate, 1),
                'total'    => $totalAnswers,
                'failures' => $wrongAnswers,
                'success'  => $totalAnswers - $wrongAnswers,
            ];
        }

        return $categoryFailures;
    }

    /**
     * Retrieves the hardest questions based on failure rate and response time.
     *
     * Executes a query to calculate the total answers, wrong answers, average response time, and failure rate
     * for each question, then formats the results.
     *
     * @return array<int, array{
     *     id: int,
     *     text: string,
     *     category: object,
     *     totalAnswers: int,
     *     failureRate: float,
     *     avgResponseTime: float
     * }>
     */
    public function getHardestQuestionsStats(): array
    {
        $questions = $this->createQueryBuilder('q')
            ->leftJoin('q.quizSessionAnswers', 'a')
            ->leftJoin('q.category', 'c')
            ->select('q, c.name as categoryName,
                      COUNT(a.id) as totalAnswers,
                      COUNT(CASE WHEN a.isCorrect = false THEN 1 END) as wrongAnswers,
                      AVG(a.time) as avgResponseTime,
                      (COUNT(CASE WHEN a.isCorrect = false THEN 1 END) * 100.0 / COUNT(a.id)) as failureRate')
            ->where('a.deletedAt IS NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->groupBy('q.id')
            ->having('COUNT(a.id) >= 5') // Au moins 5 réponses
            ->orderBy('failureRate', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return array_map(function ($result) {
            $question = $result[0]; // L'objet Question

            return [
                'id'              => $question->getId(),
                'text'            => $question->getContent(),
                'category'        => $question->getCategory(),
                'totalAnswers'    => (int) $result['totalAnswers'],
                'failureRate'     => round($result['failureRate'] ?? 0, 1),
                'avgResponseTime' => round($result['avgResponseTime'] ?? 0),
            ];
        }, $questions);
    }
}
