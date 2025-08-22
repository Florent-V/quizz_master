<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\DTO\AnswerInputDto;
use App\DTO\AnswerOutputDto;
use App\Entity\QuizSession;
use App\Entity\QuizSessionAnswer;
use App\Repository\ProposalRepository;
use App\Repository\QuestionRepository;
use App\Repository\QuizSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<AnswerInputDto, AnswerOutputDto>
 */
readonly class QuizAnswerProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuestionRepository $questionRepository,
        private ProposalRepository $proposalRepository,
        private QuizSessionRepository $quizSessionRepository,
        private Security $security,
    ) {
    }

    /**
     * @param AnswerInputDto $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): AnswerOutputDto
    {
        $quizSession = $this->retrieveQuizSession($uriVariables['id']);

        $question = $this->questionRepository->find($data->questionId);
        $proposal = $this->proposalRepository->find($data->proposalId);

        if (!$question || !$proposal) {
            throw new NotFoundHttpException('Question or proposal not found.');
        }

        $isCorrect  = $proposal->isCorrect();
        $answeredAt = new \DateTimeImmutable();
        $askedAt    = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6f', $data->askedAtTimestamp / 1000));
        $timeTaken  = $answeredAt->getTimestamp() - $askedAt->getTimestamp();

        $answer = new QuizSessionAnswer();
        $answer->setQuizSession($quizSession);
        $answer->setQuestion($question);
        $answer->setProposal($proposal);
        $answer->setIsCorrect($isCorrect);
        $answer->setAskedAt($askedAt);
        $answer->setAnsweredAt($answeredAt);
        $answer->setTime($timeTaken);

        $this->entityManager->persist($answer);

        if ($isCorrect) {
            $quizSession->setScore($quizSession->getScore() + 1);
        }

        $this->entityManager->flush();

        $correctProposal = $this->proposalRepository->findOneBy(['question' => $question, 'isCorrect' => true]);
        if (!$correctProposal) {
            throw new \LogicException('The question has no correct answer.');
        }

        return new AnswerOutputDto(
            isCorrect: $isCorrect,
            correctProposalId: $correctProposal->getId(),
            score: $quizSession->getScore()
        );
    }

    private function retrieveQuizSession(int $quizSessionId): QuizSession
    {
        $quizSession = $this->quizSessionRepository->find($quizSessionId);

        if (!$quizSession) {
            throw new NotFoundHttpException('Quiz session not found.');
        }

        if ('in_progress' !== $quizSession->getStatus() || null !== $quizSession->getFinishedAt()) {
            throw new AccessDeniedException('Quiz session is Over.');
        }

        if ($this->security->getUser() !== $quizSession->getUser()) {
            throw new AccessDeniedException('You do not own this quiz session.');
        }

        return $quizSession;
    }
}
