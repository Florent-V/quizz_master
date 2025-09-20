<?php

declare(strict_types=1);

namespace App\Controller\Quiz\API;

use App\DTO\CreateAnswerInputDto;
use App\DTO\CreateAnswerOutputDto;
use App\Entity\QuizSession;
use App\Quiz\Service\AnswerCreation\AnswerCreationValidationService;
use App\Quiz\Service\QuizAnswerService;
use App\Quiz\Service\QuizSessionService;
use App\Service\QuestionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(
    '/api/quiz-session/{id}/create-answer',
    name: 'app_quiz_create_answer',
    requirements: [
        'id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
    ],
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
        AnswerCreationValidationService $answerCreationValidationService,
    ): JsonResponse {
        try {
            $errors = $validator->validate($dto);
            if (count($errors) > 0) {
                return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
            }

            // Check QuizSession and Rules before create Answer
            $quizService->checkProcessQuizSession($quizSession);
            $answerCreationValidationService->validateCanCreateAnswer($quizSession);

            $question = $questionService->getQuestionById($dto->questionId);
            if (!$question) {
                throw $this->createNotFoundException('No valid question found.');
            }
            $quizSessionAnswer = $quizAnswerService->prepareAnswer($quizSession, $question);

            return $this->json(new CreateAnswerOutputDto(
                quizSessionAnswerId: $quizSessionAnswer->getId(),
                questionId: $question->getId(),
            ), Response::HTTP_CREATED);
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
