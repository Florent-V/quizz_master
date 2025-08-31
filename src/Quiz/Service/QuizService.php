<?php

declare(strict_types=1);

namespace App\Quiz\Service;

use App\DTO\QuizConfigurationDTO;
use App\Entity\Proposal;
use App\Entity\Question;
use App\Entity\QuizSession;
use App\Entity\QuizSessionAnswer;
use App\Entity\User;
use App\Enum\QuizSessionStatus;
use App\Quiz\Exception\InvalidAnswerException;
use App\Quiz\Exception\InvalidQuizSessionException;
use App\Quiz\Exception\NoMoreQuestionsException;
use App\Repository\ProposalRepository;
use App\Repository\QuestionRepository;
use App\Repository\QuizSessionAnswerRepository;
use App\Repository\QuizSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class QuizService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuestionRepository $questionRepository,
        private QuizSessionRepository $quizSessionRepository,
        private ProposalRepository $proposalRepository,
        private QuizSessionAnswerRepository $quizSessionAnswerRepository,
        private Security $security,
        private SerializerInterface $serializer,
    ) {
    }

    /**
     * Creates a new quiz session based on the provided configuration.
     *
     * @param QuizConfigurationDTO $dto the data transfer object containing the quiz configuration
     *
     * @return QuizSession the newly created quiz session
     */
    public function createQuizSession(QuizConfigurationDTO $dto): QuizSession
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
        $quizSession->setScore(0);

        $this->entityManager->persist($quizSession);
        $this->entityManager->flush();

        return $quizSession;
    }

    /**
     * @throws InvalidQuizSessionException
     */
    public function getQuizSession(int $quizSessionId): QuizSession
    {
        $quizSession = $this->quizSessionRepository->find($quizSessionId);
        if (!$quizSession || QuizSessionStatus::InProgress !== $quizSession->getStatus()) {
            throw new InvalidQuizSessionException('Session de quiz expirée ou invalide. Veuillez recommencer.');
        }

        return $quizSession;
    }

    /**
     * @throws NoMoreQuestionsException
     */
    public function getQuizQuestion(?int $questionId, QuizConfigurationDTO $quizDto): Question
    {
        $question = $questionId
            ? $this->questionRepository->find($questionId)
            : $this->questionRepository->findQuestionsForQuiz($quizDto, $quizDto->gameMode->getQuestionLimit())[0];

        if (!$question) {
            throw new NoMoreQuestionsException();
        }

        return $question;
    }

    /**
     * @throws NoMoreQuestionsException
     *
     * @return array<array{
     *     id: int,
     *     content: string,
     *     explanation: string|null,
     *     hint: string|null,
     *     imageName: string|null,
     *     category: array{id: int, name: string},
     *     difficulty: array{id: int, name: string},
     *     proposals: array<array{
     *         id: int,
     *         content: string,
     *         isCorrect: bool,
     *         imageName: string|null
     *     }>
     * }>
     */
    public function getNormalizedQuizQuestions(QuizConfigurationDTO $quizDto): array
    {
        $limit     = $quizDto->gameMode->getQuestionLimit();
        $questions = $this->questionRepository->findQuestionsForQuiz($quizDto, $limit);

        if (!count($questions)) {
            throw new NoMoreQuestionsException();
        }

        // @phpstan-ignore-next-line
        $questionsArray = $this->serializer->normalize($questions, 'json', [
            'groups' => ['quiz:question:read'],
        ]);

        shuffle($questionsArray);

        return $questionsArray;
    }

    /**
     * Prepares a new answer entry for a given question in a quiz session.
     *
     * @param QuizSession $quizSession the current quiz session
     * @param Question    $question    the question being answered
     *
     * @return QuizSessionAnswer the newly created (but not yet answered) answer entry
     */
    public function prepareAnswer(QuizSession $quizSession, Question $question): QuizSessionAnswer
    {
        $answer = new QuizSessionAnswer();
        $answer->setQuizSession($quizSession);
        $answer->setQuestion($question);
        $answer->setAskedAt(new \DateTimeImmutable());
        $this->entityManager->persist($answer);
        $this->entityManager->flush();

        return $answer;
    }

    /**
     * @throws InvalidAnswerException
     */
    public function getQuizSessionAnswer(int $quizSessionAnswerId): QuizSessionAnswer
    {
        $quizSessionAnswer = $this->quizSessionAnswerRepository->find($quizSessionAnswerId);
        if (!$quizSessionAnswer || null !== $quizSessionAnswer->getAnsweredAt()) {
            throw new InvalidAnswerException();
        }

        return $quizSessionAnswer;
    }

    /**
     * Retrieves a proposal by its ID and validates it against the question ID.
     *
     * @param int $proposalId the ID of the proposal to retrieve
     * @param int $questionId the ID of the question to which the proposal must belong
     *
     * @throws InvalidAnswerException if the proposal is not found or does not belong to the specified question
     *
     * @return Proposal the found proposal
     */
    public function getProposal(int $proposalId, int $questionId): Proposal
    {
        $proposal = $this->proposalRepository->find($proposalId);

        if (!$proposal || $proposal->getQuestion()->getId() !== $questionId) {
            throw new InvalidAnswerException();
        }

        return $proposal;
    }

    /**
     * Processes a user's answer, updates the answer entity, and adjusts the session score.
     *
     * @param QuizSession        $quizSession the current quiz session
     * @param QuizSessionAnswer  $answer      the answer entity to update
     * @param Proposal           $proposal    the proposal selected by the user
     * @param \DateTimeImmutable $answeredAt  the timestamp when the answer was submitted
     */
    public function processAnswer(
        QuizSession $quizSession,
        QuizSessionAnswer $answer,
        Proposal $proposal,
        \DateTimeImmutable $answeredAt,
    ): void {
        $isCorrect = $proposal->isCorrect();
        $answer->setProposal($proposal);
        $answer->setAnsweredAt($answeredAt);
        $answer->setIsCorrect($isCorrect);
        $answer->setAnsweredAt(new \DateTimeImmutable());
        $timeTaken = $answeredAt->getTimestamp() - $answer->getAskedAt()->getTimestamp();
        $answer->setTime($timeTaken);
        if ($isCorrect) {
            $quizSession->setScore($quizSession->getScore() + 1);
        }
        $this->entityManager->flush();
    }

    /**
     * Finalizes a quiz session by setting its status to 'Finished'.
     *
     * @param QuizSession $quizSession the quiz session to finish
     */
    public function processEndQuizSession(QuizSession $quizSession): void
    {
        $quizSession->setFinishedAt(new \DateTime());
        $quizSession->setStatus(QuizSessionStatus::Finished);
        $this->entityManager->flush();
    }

    /**
     * Starts a new quiz session and fetches the initial set of questions.
     *
     * @param QuizConfigurationDTO $dto the quiz configuration
     *
     * @throws \RuntimeException if no questions are found for the given configuration
     *
     * @return QuizSession the newly started quiz session
     */
    public function startQuizSession(QuizConfigurationDTO $dto): QuizSession
    {
        $questions = $this->questionRepository->findQuestionsForQuiz($dto, 50); // Limite à 50 questions max

        if (empty($questions)) {
            throw new \RuntimeException('Aucune question trouvée pour cette configuration.');
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
     * Gets the next unanswered question from the list of question IDs for the session.
     *
     * @param QuizSession $quizSession the current quiz session
     * @param int[]       $questionIds the list of all question IDs for this quiz
     *
     * @return Question|null the next question entity, or null if all questions have been answered
     */
    public function getNextQuestion(QuizSession $quizSession, array $questionIds): ?Question
    {
        $answeredQuestionIds = $this->getAnsweredQuestionIds($quizSession);

        foreach ($questionIds as $questionId) {
            if (!in_array($questionId, $answeredQuestionIds, true)) {
                return $this->entityManager->getRepository(Question::class)->find($questionId);
            }
        }

        return null;
    }

    /**
     * Submits an answer for a question, records it, and updates the quiz session.
     *
     * @param QuizSession        $quizSession      the current quiz session
     * @param Question           $question         the question being answered
     * @param Proposal|null      $selectedProposal the proposal selected by the user
     * @param \DateTimeImmutable $askedAt          the timestamp when the question was asked
     *
     * @return QuizSessionAnswer the recorded answer
     */
    public function submitAnswer(
        QuizSession $quizSession,
        Question $question,
        ?Proposal $selectedProposal,
        \DateTimeImmutable $askedAt,
    ): QuizSessionAnswer {
        $answeredAt   = new \DateTimeImmutable();
        $responseTime = $answeredAt->getTimestamp() - $askedAt->getTimestamp();

        $isCorrect = $selectedProposal?->isCorrect() ?? false;

        $answer = new QuizSessionAnswer();
        $answer->setQuizSession($quizSession);
        $answer->setQuestion($question);
        $answer->setProposal($selectedProposal);
        $answer->setIsCorrect($isCorrect);
        $answer->setTime($responseTime);
        $answer->setAskedAt($askedAt);
        $answer->setAnsweredAt($answeredAt);

        if (!$isCorrect) {
            $this->entityManager->persist($answer);
            $this->entityManager->flush();
            $this->finishQuizSession($quizSession);

            return $answer;
        }

        $quizSession->setScore($quizSession->getScore() + 1);
        $this->entityManager->persist($answer);
        $this->entityManager->flush();

        return $answer;
    }

    /**
     * Finishes the quiz session, setting the finished time and status.
     *
     * @param QuizSession       $quizSession the quiz session to finish
     * @param QuizSessionStatus $status      the final status of the quiz session
     */
    public function finishQuizSession(
        QuizSession $quizSession,
        QuizSessionStatus $status = QuizSessionStatus::Finished,
    ): void {
        $quizSession->setFinishedAt(new \DateTime());
        $quizSession->setStatus($status);
        $this->entityManager->flush();
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
     * Gets the IDs of all questions that have been answered in a quiz session.
     *
     * @param QuizSession $quizSession the quiz session
     *
     * @return int[] an array of answered question IDs
     */
    private function getAnsweredQuestionIds(QuizSession $quizSession): array
    {
        return array_map(
            fn (QuizSessionAnswer $answer) => $answer->getQuestion()->getId(),
            $quizSession->getQuizSessionAnswers()->toArray()
        );
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

    /**
     * Nettoie la session après fin du quiz.
     */
    public function clearQuizSession(SessionInterface $session): void
    {
        $session->remove('quiz_session_id');
        $session->remove('quiz_questions');
        $session->remove('current_question_index');
    }
}
