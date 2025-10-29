<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Question;
use App\Entity\QuizSession;
use App\Entity\QuizSessionAnswer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

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
    public function findQuestionIdsByQuizSessionId(Uuid $quizSessionId): array
    {
        return $this->createQueryBuilder('qsa')
            ->select('IDENTITY(qsa.question)')
            ->where('qsa.quizSession = :quizSessionId')
            ->setParameter('quizSessionId', $quizSessionId, 'uuid')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function findIfMatchesSessionAndQuestion(
        int $quizSessionAnswerId,
        Uuid $quizSessionId,
        int $questionId,
    ): ?QuizSessionAnswer {
        return $this->createQueryBuilder('qsa')
            ->where('qsa.id = :quizSessionAnswerId')
            ->andWhere('qsa.question = :questionId')
            ->andWhere('qsa.quizSession = :quizSessionId')
            ->setParameter('quizSessionAnswerId', $quizSessionAnswerId)
            ->setParameter('questionId', $questionId)
            ->setParameter('quizSessionId', $quizSessionId, 'uuid')
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

    /**
     * @return array<int, QuizSessionAnswer>
     */
    public function getQuizResults(Uuid $quizSessionId): array
    {
        return $this->createQueryBuilder('qsa')
            ->select('qsa', 'q', 'd')
            ->join('qsa.question', 'q')
            ->join('q.difficulty', 'd')
            ->where('qsa.quizSession = :quizSessionId')
            ->setParameter('quizSessionId', $quizSessionId, 'uuid')
            ->orderBy('qsa.answeredAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts the number of QuizSessionAnswer entities linked to a given QuizSession.
     *
     * @param Uuid $quizSessionId the ID of the QuizSession to filter by
     *
     * @return int the total number of QuizSessionAnswer entities associated with the given QuizSession
     */
    public function countByQuizSessionId(Uuid $quizSessionId): int
    {
        return (int) $this->createQueryBuilder('qsa')
            ->select('COUNT(qsa.id)')
            ->where('qsa.quizSession = :quizSessionId')
            ->setParameter('quizSessionId', $quizSessionId, 'uuid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Counts the number of incomplete QuizSessionAnswer entities linked to a given QuizSession.
     *
     * A QuizSessionAnswer is considered incomplete if:
     *  - its proposal is NULL, OR
     *  - its isCorrect is NULL, OR
     *  - its answeredAt is NULL.
     *
     * @param Uuid $quizSessionId the ID of the QuizSession to filter by
     *
     * @return int the number of incomplete QuizSessionAnswer entities
     */
    public function countIncompleteByQuizSessionId(Uuid $quizSessionId): int
    {
        return (int) $this->createQueryBuilder('qsa')
            ->select('COUNT(qsa.id)')
            ->where('qsa.quizSession = :quizSessionId')
            ->andWhere('qsa.proposal IS NULL OR qsa.isCorrect IS NULL OR qsa.answeredAt IS NULL')
            ->setParameter('quizSessionId', $quizSessionId, 'uuid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Finds the first incomplete QuizSessionAnswer entity linked to a given QuizSession.
     *
     * A QuizSessionAnswer is considered incomplete if:
     *  - its proposal is NULL, OR
     *  - its isCorrect is NULL, OR
     *  - its answeredAt is NULL.
     *
     * @param Uuid $quizSessionId the ID of the QuizSession to filter by
     *
     * @return QuizSessionAnswer|null the first incomplete QuizSessionAnswer entity, or null if none found
     */
    public function findFirstIncompleteByQuizSessionId(Uuid $quizSessionId): ?QuizSessionAnswer
    {
        return $this->createQueryBuilder('qsa')
            ->where('qsa.quizSession = :quizSessionId')
            ->andWhere('qsa.proposal IS NULL OR qsa.isCorrect IS NULL OR qsa.answeredAt IS NULL')
            ->setParameter('quizSessionId', $quizSessionId, 'uuid')
            ->orderBy('qsa.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Counts the number of incorrect QuizSessionAnswer entities linked to a given QuizSession.
     *
     * @param Uuid $quizSessionId the ID of the QuizSession to filter by
     *
     * @return int the number of incorrect QuizSessionAnswer entities
     */
    public function countIncorrectByQuizSessionId(Uuid $quizSessionId): int
    {
        return (int) $this->createQueryBuilder('qsa')
            ->select('COUNT(qsa.id)')
            ->where('qsa.quizSession = :quizSessionId')
            ->andWhere('qsa.isCorrect = false') // false = incorrect
            ->setParameter('quizSessionId', $quizSessionId, 'uuid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Counts the number of incorrect QuizSessionAnswer entities linked to a given QuizSession.
     *
     * @param Uuid $quizSessionId the ID of the QuizSession to filter by
     *
     * @return int the number of incorrect QuizSessionAnswer entities
     */
    public function countCorrectByQuizSessionId(Uuid $quizSessionId): int
    {
        return (int) $this->createQueryBuilder('qsa')
            ->select('COUNT(qsa.id)')
            ->where('qsa.quizSession = :quizSessionId')
            ->andWhere('qsa.isCorrect = true') // false = incorrect
            ->setParameter('quizSessionId', $quizSessionId, 'uuid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLastAnswer(Uuid $quizSessionId): ?QuizSessionAnswer
    {
        return $this->createQueryBuilder('qsa')
            ->where('qsa.quizSession = :quizSessionId')
            ->setParameter('quizSessionId', $quizSessionId, 'uuid')
            ->orderBy('qsa.answeredAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Counts all non-deleted answers.
     */
    public function getTotalCount(): int
    {
        return $this->count(['deletedAt' => null]);
    }

    /**
     * Counts all correct and non-deleted answers.
     */
    public function getCorrectAnswersCount(): int
    {
        return $this->count(['isCorrect' => true, 'deletedAt' => null]);
    }

    /**
     * Calculates the average response time (in milliseconds) for all non-deleted answers.
     */
    public function getAverageResponseTime(): int
    {
        $result = $this->createQueryBuilder('a')
            ->select('AVG(a.time)')
            ->where('a.time IS NOT NULL')
            ->andWhere('a.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Exports answer data to a structured array for reporting.
     *
     * @return array<int, array{
     *     ID: int,
     *     Session: string,
     *     Question: string,
     *     'Réponse choisie': string,
     *     Correcte: string,
     *     Score: int,
     *     'Temps (ms)': int,
     *     Catégorie: string,
     *     'Posée le': string,
     *     'Répondue le': string
     * }>
     */
    public function exportToArray(): array
    {
        $answers = $this->createQueryBuilder('a')
            ->leftJoin('a.quizSession', 'q')
            ->leftJoin('a.question', 'qu')
            ->leftJoin('a.proposal', 'p')
            ->leftJoin('qu.category', 'c')
            ->select('a.id, q.pseudo, qu.text as questionText, p.text as proposalText,
                  a.isCorrect, a.time, a.score, c.name as categoryName, 
                  a.askedAt, a.answeredAt')
            ->where('a.deletedAt IS NULL')
            ->orderBy('a.askedAt', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $exportData = [];
        foreach ($answers as $answer) {
            $exportData[] = [
                'ID'              => $answer['id'],
                'Session'         => $answer['pseudo'],
                'Question'        => substr($answer['questionText'], 0, 100) . '...',
                'Réponse choisie' => $answer['proposalText'] ?? 'Pas de réponse',
                'Correcte'        => $answer['isCorrect'] ? 'Oui' : 'Non',
                'Score'           => $answer['score']        ?? 0,
                'Temps (ms)'      => $answer['time']         ?? 0,
                'Catégorie'       => $answer['categoryName'] ?? 'Non définie',
                'Posée le'        => $answer['askedAt']->format('d/m/Y H:i:s'),
                'Répondue le'     => $answer['answeredAt']
                    ? $answer['answeredAt']->format('d/m/Y H:i:s')
                    : 'Pas répondue',
            ];
        }

        return $exportData;
    }

    /**
     * Calculates the average response time (in milliseconds) for a specific question.
     */
    public function getAverageTimeForQuestion(Question $question): float
    {
        $result = (int) $this->createQueryBuilder('a')
            ->select('AVG(a.time)')
            ->where('a.question = :question')
            ->andWhere('a.time IS NOT NULL')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('question', $question)
            ->getQuery()
            ->getSingleScalarResult();

        return round($result, 2);
    }

    /**
     * Analyzes response times distribution (very fast, fast, normal, slow).
     *
     * @return array{
     *     avgTime?: float,
     *     minTime?: float,
     *     maxTime?: float,
     *     veryFast?: int,
     *     fast?: int,
     *     normal?: int,
     *     slow?: int
     * }
     */
    public function getResponseTimeAnalysis(): array
    {
        $result = $this->createQueryBuilder('a')
            ->select('
            AVG(a.time) as avgTime,
            MIN(a.time) as minTime,
            MAX(a.time) as maxTime,
            SUM(CASE WHEN a.time < 2 THEN 1 ELSE 0 END) as veryFast,
            SUM(CASE WHEN a.time BETWEEN 2 AND 5 THEN 1 ELSE 0 END) as fast,
            SUM(CASE WHEN a.time BETWEEN 5 AND 10 THEN 1 ELSE 0 END) as normal,
            SUM(CASE WHEN a.time > 10 THEN 1 ELSE 0 END) as slow
        ')
            ->where('a.time IS NOT NULL')
            ->andWhere('a.deletedAt IS NULL')
            ->getQuery()
            ->getOneOrNullResult();

        return $result ?: [];
    }

    /**
     * Retrieves performance statistics grouped by question difficulty.
     *
     * @return array<int, array{
     *     name: string,
     *     color: string,
     *     totalAnswers: int,
     *     successRate: float,
     *     avgTime: float
     * }>
     */
    public function getPerformanceByDifficulty(): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.question', 'q')
            ->leftJoin('q.difficulty', 'd')
            ->select('d.name, d.color, COUNT(a.id) as totalAnswers,
                  AVG(CASE WHEN a.isCorrect = true THEN 1.0 ELSE 0.0 END) * 100 as successRate,
                  AVG(a.time) as avgTime')
            ->where('d.id IS NOT NULL')
            ->andWhere('a.deletedAt IS NULL')
            ->groupBy('d.id, d.name')
            ->orderBy('successRate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts answers without an associated quiz session.
     */
    public function getOrphanAnswers(): int
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.quizSession', 'q')
            ->select('COUNT(a.id)')
            ->where('q.id IS NULL')
            ->andWhere('a.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Counts questions asked in the last hour.
     */
    public function getQuestionsPerHour(): int
    {
        $oneHourAgo = new \DateTime('-1 hour');

        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.askedAt >= :oneHourAgo')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('oneHourAgo', $oneHourAgo)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Counts answers with a response time exceeding 30 seconds.
     */
    public function getSlowQueriesCount(): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.time > 30000') // Plus de 30 secondes
            ->andWhere('a.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Analyzes answers that timed out (not answered within 5 minutes).
     *
     * @return array{
     *     totalTimeouts: int,
     *     avgTimeoutTime: float
     * }
     */
    public function getTimeoutAnalysis(): array
    {
        $result = $this->createQueryBuilder('a')
            ->select('COUNT(a.id) as totalTimeouts, AVG(a.time) as avgTimeoutTime')
            ->where('a.answeredAt IS NULL')
            ->andWhere('a.askedAt < :cutoff') // Questions posées il y a plus de 5 minutes
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('cutoff', new \DateTime('-5 minutes'))
            ->getQuery()
            ->getOneOrNullResult();

        return $result ?: ['totalTimeouts' => 0, 'avgTimeoutTime' => 0];
    }

    /**
     * Calcule le taux de réussite global.
     */
    public function getGlobalSuccessRate(): float
    {
        $result = $this->createQueryBuilder('a')
            ->select('
                COUNT(a.id) as total,
                SUM(CASE WHEN a.isCorrect = true THEN 1 ELSE 0 END) as correct
            ')
            ->where('a.deletedAt IS NULL')
            ->getQuery()
            ->getOneOrNullResult();

        if (!$result || 0 == $result['total']) {
            return 0.0;
        }

        return round(($result['correct'] / $result['total']) * 100, 2);
    }
}
