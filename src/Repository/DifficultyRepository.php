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
        $this->getEntityManager()->getFilters()->disable('softdeleteable');

        $count = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $this->getEntityManager()->getFilters()->enable('softdeleteable');

        return (int) $count;
    }

    public function getDeletedCount(): int
    {
        $this->getEntityManager()->getFilters()->disable('softdeleteable');

        $count = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.deletedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $this->getEntityManager()->getFilters()->enable('softdeleteable');

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
}
