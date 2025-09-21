<?php

declare(strict_types=1);

namespace App\Quiz\Service;

use App\Entity\Proposal;
use App\Entity\Question;
use App\Entity\QuizSession;
use App\Entity\QuizSessionAnswer;
use App\Enum\QuizSessionStatus;
use App\Quiz\Exception\QuizBadRequestException;
use App\Quiz\Exception\QuizConflictException;
use App\Repository\ProposalRepository;
use App\Repository\QuestionRepository;
use App\Repository\QuizSessionAnswerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class QuizAnswerService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProposalRepository $proposalRepository,
        private QuizSessionAnswerRepository $quizSessionAnswerRepository,
        private OrphanAnswerCounter $orphanAnswerCounter,
        private QuestionRepository $questionRepository,
        private ScoreCalculatorService $scoreCalculatorService,
    ) {
    }

    /**
     * Prepares a new answer entry for a given question in a quiz session.
     *
     * @param QuizSession $quizSession the current quiz session
     * @param Question    $question    the question being answered
     *
     * @throws QuizConflictException
     *
     * @return QuizSessionAnswer the newly created (but not yet answered) answer entry
     */
    public function prepareAnswer(QuizSession $quizSession, Question $question): QuizSessionAnswer
    {
        // Check if the question has already been answered in this session
        $existingAnswer = $this->quizSessionAnswerRepository->findOneBy([
            'quizSession' => $quizSession,
            'question'    => $question,
        ]);

        if ($existingAnswer) {
            throw new QuizConflictException('Answer already exists for this question');
        }

        $answer = new QuizSessionAnswer();
        $answer->setQuizSession($quizSession);
        $answer->setQuestion($question);
        $answer->setAskedAt(new \DateTimeImmutable());
        $this->entityManager->persist($answer);
        $this->entityManager->flush();

        return $answer;
    }

    /**
     * @throws QuizBadRequestException
     */
    public function getQuizSessionAnswer(int $quizSessionAnswerId): QuizSessionAnswer
    {
        $quizSessionAnswer = $this->quizSessionAnswerRepository->find($quizSessionAnswerId);
        if (!$quizSessionAnswer || null !== $quizSessionAnswer->getAnsweredAt()) {
            throw new QuizBadRequestException('Session inconnue ou invalide');
        }

        return $quizSessionAnswer;
    }

    /**
     * @throws QuizBadRequestException
     */
    public function retrieveQuizSessionAnswer(
        int $quizSessionAnswerId,
        Uuid $quizSessionId,
        int $questionId,
    ): QuizSessionAnswer {
        $quizSessionAnswer = $this->quizSessionAnswerRepository->findIfMatchesSessionAndQuestion(
            $quizSessionAnswerId,
            $quizSessionId,
            $questionId
        );
        if (!$quizSessionAnswer || null !== $quizSessionAnswer->getAnsweredAt()) {
            throw new QuizBadRequestException('Session inconnue ou invalide');
        }

        return $quizSessionAnswer;
    }

    /**
     * Retrieves a proposal by its ID and validates it against the question ID.
     *
     * @param int $proposalId the ID of the proposal to retrieve
     * @param int $questionId the ID of the question to which the proposal must belong
     *
     * @throws QuizBadRequestException if the proposal is not found or does not belong to the specified question
     *
     * @return Proposal the found proposal
     */
    public function getProposal(int $proposalId, int $questionId): Proposal
    {
        $proposal = $this->proposalRepository->find($proposalId);

        if (!$proposal || $proposal->getQuestion()->getId() !== $questionId) {
            throw new QuizBadRequestException('Proposition inconnue ou invalide');
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
        $score = $this->scoreCalculatorService->calculateScore($answer);
        $answer->setScore($score);
        if ($isCorrect) {
            $quizSession->setScore($quizSession->getScore() + $score);
        }
        $this->entityManager->persist($answer);
        $this->entityManager->persist($quizSession);
        $this->entityManager->flush();
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
     * Vérifie qu'il n'y a pas de réponse en attente (non répondue).
     *
     * @throws QuizConflictException
     */
    public function validateNoPendingAnswer(QuizSession $quizSession): void
    {
        $countPendingAnswer = $this->orphanAnswerCounter->count($quizSession);

        if (0 !== $countPendingAnswer) {
            throw new QuizConflictException(
                'Une question est déjà en cours. Vous devez y répondre avant de pouvoir passer à la suivante.'
            );
        }
    }

    public function findGoodAnswerId(int $questionId): int
    {
        return $this->questionRepository->findGoodAnswerId($questionId);
    }
}
