<?php

declare(strict_types=1);

namespace App\Repository\Builder;

use App\Entity\Category;
use App\Entity\Difficulty;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * @phpstan-type QuestionId int
 */
class QuestionQueryBuilder
{
    private QueryBuilder $qb;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Creates a new QueryBuilder instance.
     *
     * @return $this
     */
    public function create(): self
    {
        $this->qb = $this->entityManager->createQueryBuilder()
            ->select('q')
            ->from(Question::class, 'q')
            ->leftJoin('q.proposals', 'p')
            ->join('q.category', 'c')
            ->where('q.deletedAt IS NULL')
            ->andWhere('q.isActive = :isActive')
            ->andWhere('c.isActive = :isActive')
            ->setParameter('isActive', true);

        return $this;
    }

    /**
     * Filters questions by category.
     *
     * @param Category|null $category    The main category
     * @param Category|null $subCategory The specific sub-category
     *
     * @return $this
     */
    public function withCategory(?Category $category, ?Category $subCategory): self
    {
        if ($subCategory) {
            $this->qb->andWhere('q.category = :category')
                ->setParameter('category', $subCategory);

            return $this;
        }

        if ($category) {
            $categoryIds = array_map(
                fn (Category $c) => $c->getId(),
                $category->getActiveChildren()->toArray()
            );
            $categoryIds[] = $category->getId();

            $this->qb->andWhere('q.category IN (:categoryIds)')
                ->setParameter('categoryIds', $categoryIds);
        }

        return $this;
    }

    /**
     * Filters questions by difficulties.
     *
     * @param array<int, Difficulty> $difficulties an array of Difficulty entities
     *
     * @return $this
     */
    public function withDifficulties(array $difficulties): self
    {
        if ([] !== $difficulties) {
            $this->qb->andWhere('q.difficulty IN (:difficulties)')
                ->setParameter('difficulties', $difficulties);
        }

        return $this;
    }

    /**
     * Excludes questions with the given IDs.
     *
     * @param array<int, QuestionId> $excludedQuestionIds an array of question IDs to exclude
     *
     * @return $this
     */
    public function excluding(array $excludedQuestionIds): self
    {
        if ([] !== $excludedQuestionIds) {
            $this->qb->andWhere('q.id NOT IN (:excluded)')
                ->setParameter('excluded', $excludedQuestionIds);
        }

        return $this;
    }

    /**
     * Filters for valid questions only.
     * A valid question must have exactly 4 proposals and exactly one correct proposal.
     *
     * @return $this
     */
    public function onlyValid(): self
    {
        $this->qb->groupBy('q.id')
            ->having('COUNT(p.id) = 4 AND SUM(CASE WHEN p.isCorrect = 1 THEN 1 ELSE 0 END) = 1');

        return $this;
    }

    /**
     * Orders the results randomly.
     *
     * @return $this
     */
    public function randomized(): self
    {
        $this->qb->addSelect('RAND() as HIDDEN rand')
            ->orderBy('rand');

        return $this;
    }

    /**
     * Limits the number of results.
     *
     * @param int|null $limit the maximum number of results to return
     *
     * @return $this
     */
    public function limit(?int $limit): self
    {
        if (null !== $limit) {
            $this->qb->setMaxResults($limit);
        }

        return $this;
    }

    /**
     * Gets the result of the query.
     *
     * @return array<int, Question>
     */
    public function getResult(): array
    {
        return $this->qb->getQuery()->getResult();
    }
}
