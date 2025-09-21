<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\QuizSession;
use App\Enum\QuizSessionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuizSession>
 */
class QuizSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizSession::class);
    }

    /**
     * @return QuizSession[]
     */
    public function findStaleInProgressSessions(): array
    {
        return $this->createQueryBuilder('qs')
            ->andWhere('qs.status = :status')
            ->andWhere('qs.createdAt < :date')
            ->setParameter('status', QuizSessionStatus::InProgress)
            ->setParameter('date', new \DateTimeImmutable('-1 day'))
            ->getQuery()
            ->getResult()
        ;
    }

}
