<?php

declare(strict_types=1);

namespace App\Controller\Quiz\API;

use App\DTO\CreateAnswerInputDto;
use App\DTO\CreateAnswerOutputDto;
use App\Entity\QuizSession;
use App\Quiz\Service\QuizAnswerService;
use App\Quiz\Service\QuizSessionService;
use App\Service\QuestionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(
    '/quiz-sessions/{id}/create-answer',
    name: 'app_quiz_create_answer',
    methods: ['POST']
)]
class CreateAnswer extends AbstractController
{
    public function __invoke(
        QuizSession $quizSession,
        #[MapRequestPayload] CreateAnswerInputDto $dto,
        ValidatorInterface $validator,
        QuizSessionService $quizService,
        QuizAnswerService $quizAnswerService,
        QuestionService $questionService,
    ): JsonResponse {
        try {
            $errors = $validator->validate($dto);
            if (count($errors) > 0) {
                return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
            }
            $quizService->checkProcessQuizSession($quizSession);

            $question = $questionService->getQuestionById($dto->questionId);
            if (!$question) {
                throw $this->createNotFoundException('No valid question found.');
            }

            $quizSessionAnswer = $quizAnswerService->prepareAnswer($quizSession, $question);

            return $this->json(new CreateAnswerOutputDto(
                quizSessionAnswerId: $quizSessionAnswer->getId(),
                questionId: $question->getId(),
            ), Response::HTTP_CREATED);
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
