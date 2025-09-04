<?php

declare(strict_types=1);

namespace App\Controller\Quiz\API;

use App\DTO\SubmitAnswerInputDto;
use App\DTO\SubmitAnswerOutputDto;
use App\Entity\QuizSession;
use App\Quiz\Service\QuizService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(
    '/quiz-sessions/{id}/submit-answer',
    name: 'app_quiz_submit_answer',
    methods: ['POST']
)]
class SubmitAnswer extends AbstractController
{
    public function __invoke(
        QuizSession $quizSession,
        #[MapRequestPayload] SubmitAnswerInputDto $dto,
        QuizService $quizService,
        ValidatorInterface $validator,
    ): JsonResponse {
        try {
            $answeredAt = new \DateTimeImmutable();
            $errors     = $validator->validate($dto);
            if (count($errors) > 0) {
                return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
            }
            $quizService->checkProcessQuizSession($quizSession);

            $quizSessionAnswer = $quizService->retrieveQuizSessionAnswer(
                $dto->quizSessionAnswerId,
                $quizSession->getId(),
                $dto->questionId
            );

            $proposal = $quizService->getProposal($dto->proposalId, $dto->questionId);
            $quizService->processAnswer($quizSession, $quizSessionAnswer, $proposal, $answeredAt);


            return $this->json(new SubmitAnswerOutputDto(
                quizSessionAnswerId: $quizSessionAnswer->getId(),
                isCorrect: $quizSessionAnswer->isCorrect(),
                timeSpent: $quizSessionAnswer->getTime(),
                score: $quizSession->getScore() ?? 0,
            ), Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                $e instanceof HttpException
                    ? $e->getStatusCode()
                    : Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
