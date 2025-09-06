<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTO\QuizConfigurationDTO;
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
            ->where('q.deletedAt IS NULL');

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
     * @return array<int, Question>
     */
    public function findQuestionsForQuiz(
        QuizConfigurationDTO $quizDto,
        ?int $limit,
    ): array {
        $qb = $this->createQueryBuilder('q')
            ->leftJoin('q.proposals', 'p')
            ->where('q.deletedAt IS NULL');

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
    public function countQuestionsForQuiz(QuizConfigurationDTO $quizDto): int
    {
        $qb = $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.deletedAt IS NULL'); // Exclure les questions supprimées

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
        if ($quizDto->difficulties && !empty($quizDto->difficulties)) {
            $difficultyIds = $quizDto->getDifficultyIds();
            $qb->andWhere('q.difficulty IN (:difficulties)')
                ->setParameter('difficulties', $difficultyIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
