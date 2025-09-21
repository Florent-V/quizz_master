<?php

declare(strict_types=1);

namespace App\Quiz\Service;

use App\DTO\HydratedQuizConfigurationDTO;
use App\Entity\Proposal;
use App\Entity\Question;
use App\Entity\QuizSession;
use App\Entity\QuizSessionAnswer;
use App\Entity\User;
use App\Enum\QuizSessionStatus;
use App\Quiz\Exception\QuizBadRequestException;
use App\Quiz\Exception\QuizConflictException;
use App\Repository\QuestionRepository;
use App\Repository\QuizSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

final readonly class QuizSessionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuestionRepository $questionRepository,
        private QuizSessionRepository $quizSessionRepository,
        private Security $security,
        private QuizSessionGuard $quizSessionGuard,
    ) {
    }

    /**
     * Creates a new quiz session based on the provided configuration.
     *
     * @param HydratedQuizConfigurationDTO $dto the data transfer object containing the quiz configuration
     *
     * @return QuizSession the newly created quiz session
     */
    public function createQuizSession(HydratedQuizConfigurationDTO $dto): QuizSession
    {
        $quizSession = new QuizSession();
        /** @var ?User $user */
        $user = $this->security->getUser();
        if ($user) {
            $quizSession->setUser($user);
        }
        $quizSession->setPseudo($dto->pseudo);
        $quizSession->setStartedAt(new \DateTime());
        $quizSession->setGameMode($dto->gameMode);
        $quizSession->setStatus(QuizSessionStatus::InProgress);
        $quizSession->setCategory($dto->category);
        $quizSession->setSubCategory($dto->subCategory);
        foreach ($dto->difficulties ?? [] as $difficulty) {
            $quizSession->addDifficulty($difficulty);
        }
        $quizSession->setScore(0);

        $this->entityManager->persist($quizSession);
        $this->entityManager->flush();

        return $quizSession;
    }

    /**
     * @throws QuizBadRequestException
     */
    public function getQuizSession(Uuid $quizSessionId): QuizSession
    {
        $quizSession = $this->quizSessionRepository->find($quizSessionId);
        if (!$quizSession || QuizSessionStatus::InProgress !== $quizSession->getStatus()) {
            throw new QuizBadRequestException('Session de quiz inconnue ou invalide. Veuillez recommencer.');
        }

        return $quizSession;
    }

    public function retrieveQuizSession(Uuid $quizSessionId): QuizSession
    {
        $quizSession = $this->quizSessionRepository->find($quizSessionId);

        $this->quizSessionGuard->guardSessionExists($quizSession);
        $this->quizSessionGuard->guardSessionIsInProgress($quizSession);
        $this->quizSessionGuard->guardUserOwnsSession($quizSession);

        return $quizSession;
    }

    public function checkProcessQuizSession(QuizSession $quizSession): void
    {
        $this->quizSessionGuard->guardSessionIsInProgress($quizSession);
        $this->quizSessionGuard->guardUserOwnsSession($quizSession);
    }

    /**
     * Finalizes a quiz session by setting its status to 'Finished'.
     * This method is idempotent.
     *
     * @param QuizSession $quizSession the quiz session to finish
     */
    public function processEndQuizSession(QuizSession $quizSession): void
    {
        if (QuizSessionStatus::Finished === $quizSession->getStatus()) {
            return; // Already finished, do nothing.
        }

        $quizSession->setFinishedAt(new \DateTime());
        $quizSession->setStatus(QuizSessionStatus::Finished);
        $this->entityManager->flush();
    }

    /**
     * Finish Quiz Session.
     */
    public function finishQuizSession(QuizSession $quizSession): void
    {
        $quizSession->setStatus(QuizSessionStatus::Finished);
        $quizSession->setFinishedAt(new \DateTime());

        $this->entityManager->flush();
    }

    /**
     * Cancel Quiz Session.
     */
    public function cancelQuizSession(QuizSession $quizSession): void
    {
        $quizSession->setStatus(QuizSessionStatus::Cancelled);
        $quizSession->setFinishedAt(new \DateTime());

        $this->entityManager->flush();
    }

    /**
     * Failed Quiz Session.
     */
    public function failQuizSession(QuizSession $quizSession): void
    {
        $quizSession->setStatus(QuizSessionStatus::Failed);
        $quizSession->setFinishedAt(new \DateTime());

        $this->entityManager->flush();
    }

    /**
     * Starts a new quiz session and fetches the initial set of questions.
     *
     * @param HydratedQuizConfigurationDTO $dto the quiz configuration
     *
     * @throws QuizConflictException if no questions are found for the given configuration
     *
     * @return QuizSession the newly started quiz session
     */
    public function startQuizSession(HydratedQuizConfigurationDTO $dto): QuizSession
    {
        $questions = $this->questionRepository->findQuestionsForQuiz($dto, 50); // Limite à 50 questions max

        if (empty($questions)) {
            throw new QuizConflictException('Aucune question trouvée pour cette configuration.');
        }

        $quizSession = new QuizSession();
        $quizSession->setPseudo($dto->pseudo);
        $quizSession->setGameMode($dto->gameMode);
        $quizSession->setScore(0);
        $quizSession->setStartedAt(new \DateTime());
        $quizSession->setStatus(QuizSessionStatus::InProgress);

        /** @var ?User $user */
        $user = $this->security->getUser();
        if ($user) {
            $quizSession->setUser($user);
        }

        $this->entityManager->persist($quizSession);
        $this->entityManager->flush();

        // Stocker les IDs des questions dans la session
        $questionIds = array_map(fn (Question $q) => $q->getId(), $questions);
        shuffle($questionIds); // Mélanger les questions

        return $quizSession;
    }

    /**
     * Calculates and returns statistics for a completed quiz session.
     *
     * @param QuizSession $quizSession the quiz session
     *
     * @return array<string, mixed> an array of statistics
     */
    public function getQuizStatistics(QuizSession $quizSession): array
    {
        $answers = $quizSession->getQuizSessionAnswers()->toArray();

        $totalQuestions = count($answers);
        $correctAnswers = array_filter($answers, fn (QuizSessionAnswer $answer) => $answer->isCorrect());
        $totalCorrect   = count($correctAnswers);

        $totalTime   = array_reduce($answers, fn (int $sum, QuizSessionAnswer $answer) => $sum + $answer->getTime(), 0);
        $averageTime = $totalQuestions > 0 ? round($totalTime / $totalQuestions, 2) : 0;

        return [
            'totalQuestions' => $totalQuestions,
            'correctAnswers' => $totalCorrect,
            'totalTime'      => $totalTime,
            'averageTime'    => $averageTime,
            'accuracy'       => $totalQuestions > 0 ? round(($totalCorrect / $totalQuestions) * 100, 2) : 0,
            'answers'        => $answers,
        ];
    }

    /**
     * Calcule les statistiques d'une session de quiz.
     *
     * @return array{
     *      totalTime: int,
     *      averageTime: float,
     *      correctAnswers: int,
     *      totalQuestions: int,
     *      successRate: float,
     *      questionStats: array<array{
     *          question: Question,
     *          proposal: Proposal,
     *          isCorrect: bool,
     *          time: int,
     *          difficulty: string
     *      }>
     *  }
     */
    public function calculateQuizStatistics(QuizSession $quizSession): array
    {
        $answers        = $quizSession->getQuizSessionAnswers();
        $totalTime      = 0;
        $correctAnswers = 0;
        $questionStats  = [];

        foreach ($answers as $answer) {
            $totalTime += $answer->getTime();
            if ($answer->isCorrect()) {
                ++$correctAnswers;
            }

            $questionStats[] = [
                'question'   => $answer->getQuestion(),
                'proposal'   => $answer->getProposal(),
                'isCorrect'  => $answer->isCorrect(),
                'time'       => $answer->getTime(),
                'difficulty' => $answer->getQuestion()->getDifficulty()->getName(),
            ];
        }

        $totalQuestions = $answers->count();
        $averageTime    = $totalQuestions > 0 ? round($totalTime / $totalQuestions, 2) : 0;
        $successRate    = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;

        return [
            'totalTime'      => $totalTime,
            'averageTime'    => $averageTime,
            'correctAnswers' => $correctAnswers,
            'totalQuestions' => $totalQuestions,
            'successRate'    => $successRate,
            'questionStats'  => $questionStats,
        ];
    }
}
