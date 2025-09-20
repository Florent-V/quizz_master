<?php

declare(strict_types=1);

namespace App\Controller\Quiz\API;

use App\DTO\SubmitAnswerInputDto;
use App\DTO\SubmitAnswerOutputDto;
use App\Entity\QuizSession;
use App\Quiz\Service\QuizAnswerService;
use App\Quiz\Service\QuizSessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(
    '/api/quiz-session/{id}/submit-answer',
    name: 'app_quiz_submit_answer',
    requirements: [
        'id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
    ],
    methods: ['POST']
)]
class SubmitAnswer extends AbstractController
{
    public function __invoke(
        QuizSession $quizSession,
        #[MapRequestPayload] SubmitAnswerInputDto $dto,
        QuizSessionService $quizService,
        QuizAnswerService $quizAnswerService,
        ValidatorInterface $validator,
    ): JsonResponse {
        try {
            $answeredAt = new \DateTimeImmutable();
            $errors     = $validator->validate($dto);
            if (count($errors) > 0) {
                return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
            }
            $quizService->checkProcessQuizSession($quizSession);

            $quizSessionAnswer = $quizAnswerService->retrieveQuizSessionAnswer(
                $dto->quizSessionAnswerId,
                $quizSession->getId(),
                $dto->questionId
            );

            $proposal = $quizAnswerService->getProposal($dto->proposalId, $dto->questionId);
            $quizAnswerService->processAnswer($quizSession, $quizSessionAnswer, $proposal, $answeredAt);


            return $this->json(new SubmitAnswerOutputDto(
                quizSessionAnswerId: $quizSessionAnswer->getId(),
                goodAnswerId: $quizAnswerService->findGoodAnswerId($dto->questionId),
                isCorrect: $quizSessionAnswer->isCorrect(),
                timeSpent: $quizSessionAnswer->getTime(),
                score: $quizSession->getScore() ?? 0,
            ), Response::HTTP_OK);
        } catch (\RuntimeException $e) {
            // Gestion des réponses en attente - session toujours active
            return $this->json(
                [
                    'error' => $e->getMessage(),
                ],
                0 !== $e->getCode() ? $e->getCode() : Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
