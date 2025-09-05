<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\QuizSession;
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

    /**
     * Compte le nombre de réponses données dans une session.
     */
    public function countAnsweredQuestions(QuizSession $quizSession): int
    {
        return $this->createQueryBuilder('qsa')
            ->select('COUNT(qsa.id)')
            ->where('qsa.quizSession = :quizSession')
            ->andWhere('qsa.answeredAt IS NOT NULL')
            ->setParameter('quizSession', $quizSession)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve une réponse en cours (non répondue) pour une question donnée.
     */
    public function findPendingAnswerForQuestion(QuizSession $quizSession, int $questionId): ?QuizSessionAnswer
    {
        return $this->createQueryBuilder('qsa')
            ->where('qsa.quizSession = :quizSession')
            ->andWhere('qsa.question = :questionId')
            ->andWhere('qsa.answeredAt IS NULL')
            ->setParameter('quizSession', $quizSession)
            ->setParameter('questionId', $questionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère les statistiques de temps de réponse pour une session.
     *
     * @return array{
     *      averageTime: float|null,
     *      minTime: int|null,
     *      maxTime: int|null,
     *      totalAnswers: int
     *  }|null
     */
    public function getResponseTimeStats(QuizSession $quizSession): ?array
    {
        return $this->createQueryBuilder('qsa')
            ->select([
                'AVG(qsa.time) as averageTime',
                'MIN(qsa.time) as minTime',
                'MAX(qsa.time) as maxTime',
                'COUNT(qsa.id) as totalAnswers',
            ])
            ->where('qsa.quizSession = :quizSession')
            ->andWhere('qsa.answeredAt IS NOT NULL')
            ->andWhere('qsa.time IS NOT NULL')
            ->setParameter('quizSession', $quizSession)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère le score actuel d'une session.
     */
    public function getCurrentScore(QuizSession $quizSession): int
    {
        $result = $this->createQueryBuilder('qsa')
            ->select('COUNT(qsa.id) as correctAnswers')
            ->where('qsa.quizSession = :quizSession')
            ->andWhere('qsa.isCorrect = true')
            ->setParameter('quizSession', $quizSession)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * Vérifie si une session a des réponses en attente.
     */
    public function hasPendingAnswers(QuizSession $quizSession): bool
    {
        $count = $this->createQueryBuilder('qsa')
            ->select('COUNT(qsa.id)')
            ->where('qsa.quizSession = :quizSession')
            ->andWhere('qsa.answeredAt IS NULL')
            ->setParameter('quizSession', $quizSession)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
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
