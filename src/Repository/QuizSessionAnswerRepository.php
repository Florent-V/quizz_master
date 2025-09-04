<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\QuizSessionAnswer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuizSessionAnswer>
 */
class QuizSessionAnswerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizSessionAnswer::class);
    }

    /**
     * @return int[]
     */
    public function findQuestionIdsByQuizSessionId(int $quizSessionId): array
    {
        return $this->createQueryBuilder('qsa')
            ->select('IDENTITY(qsa.question)')
            ->where('qsa.quizSession = :quizSessionId')
            ->setParameter('quizSessionId', $quizSessionId)
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function findIfMatchesSessionAndQuestion(
        int $quizSessionAnswerId,
        int $quizSessionId,
        int $questionId,
    ): ?QuizSessionAnswer {
        return $this->createQueryBuilder('qsa')
            ->where('qsa.id = :quizSessionAnswerId')
            ->andWhere('qsa.question = :questionId')
            ->andWhere('qsa.quizSession = :quizSessionId')
            ->setParameter('quizSessionAnswerId', $quizSessionAnswerId)
            ->setParameter('questionId', $questionId)
            ->setParameter('quizSessionId', $quizSessionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    //    /**
    //     * @return QuizSessionAnswer[] Returns an array of QuizSessionAnswer objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('q')
    //            ->andWhere('q.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('q.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?QuizSessionAnswer
    //    {
    //        return $this->createQueryBuilder('q')
    //            ->andWhere('q.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
