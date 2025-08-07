<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Difficulty;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Difficulty>
 */
class DifficultyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Difficulty::class);
    }

    public function getTotalCount(): int
    {
        $count = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    /**
     * @return array<string,mixed>
     */
    public function getStatistics(int $difficultyId): array
    {
        $difficulty = $this->find($difficultyId);

        if (!$difficulty instanceof Difficulty) {
            throw new \InvalidArgumentException('Difficulté non trouvée');
        }

        return [
            'difficulty'       => $difficulty,
            'questions_count'  => $difficulty->getQuestions()->count(),
            'created_days_ago' => $difficulty->getCreatedAt() ?
                (new \DateTime())->diff($difficulty->getCreatedAt())->days : 0,
        ];
    }

    /**
     * @return array<array{name: string, question_count: int, level: int, color: string}>
     */
    public function getQuestionCountByDifficulty(): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('d.name, COUNT(q.id) as question_count, d.level, d.color')
            ->leftJoin('d.questions', 'q')
            ->groupBy('d.name, d.level, d.color')
            ->orderBy('d.level', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
