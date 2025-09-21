<?php

declare(strict_types=1);

namespace App\Quiz\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\DTO\AnswerInputDto;
use App\DTO\AnswerOutputDto;
use App\Entity\QuizSessionAnswer;
use App\Quiz\Exception\QuizNotFoundException;
use App\Quiz\Service\QuizSessionService;
use App\Repository\ProposalRepository;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AnswerInputDto, AnswerOutputDto>
 */
readonly class QuizAnswerProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuizSessionService $quizService,
        private QuestionRepository $questionRepository,
        private ProposalRepository $proposalRepository,
    ) {
    }

    /**
     * @param AnswerInputDto $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): AnswerOutputDto
    {
        $quizSessionId = Uuid::fromString($uriVariables['id']);
        $quizSession   = $this->quizService->retrieveQuizSession($quizSessionId);

        $question = $this->questionRepository->find($data->questionId);
        $proposal = $this->proposalRepository->find($data->proposalId);

        if (!$question || !$proposal) {
            throw new QuizNotFoundException('Question or proposal not found.');
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
}
