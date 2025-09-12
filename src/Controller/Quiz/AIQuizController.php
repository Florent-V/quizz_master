<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\DTO\AIQuizDTO;
use App\Enum\GameMode;
use App\Exception\AIQuizGenerationException;
use App\Exception\InvalidQuizThemeException;
use App\Form\AIQuizFormType;
use App\Quiz\Service\QuizConfigurationService;
use App\Quiz\Service\QuizSessionService;
use App\Service\AIQuizGeneratorService;
use App\Service\AIQuizImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class AIQuizController extends AbstractController
{
    #[Route('/quiz/ai', name: 'app_quiz_ai', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        AIQuizGeneratorService $aiQuizGenerator,
        QuizSessionService $quizService,
        AIQuizImportService $aiQuizImportService,
        EntityManagerInterface $entityManager,
        QuizConfigurationService $quizConfigurationService,
        SerializerInterface $serializer,
    ): Response {
        $aiQuizDto = new AIQuizDTO();
        $form      = $this->createForm(AIQuizFormType::class, $aiQuizDto);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $aiGeneratedQuestions = $aiQuizGenerator->generateQuestions($aiQuizDto);

                $questions = $aiQuizImportService->persistQuestions($aiGeneratedQuestions, $aiQuizDto);
                $configDto = $quizConfigurationService->createValidatedDto(
                    $questions['category'],
                    $questions['subCategory'],
                    [$aiQuizDto->difficulty->getId()],
                    GameMode::TwentyQuestions,
                    'test'
                );
                // Créer et persister la session de quiz
                $quizSession = $quizService->createQuizSession($configDto);
                if (empty($questions['questions'])) {
                    $this->addFlash(
                        'error',
                        'L\'IA n\'a pas pu générer de questions pour ce thème. Veuillez essayer un autre sujet.'
                    );

                    return $this->redirectToRoute('app_quiz_ai');
                }

                // Normaliser les questions pour le composant front
                // @phpstan-ignore-next-line
                $questionsArray = $serializer->normalize($questions['questions'], 'json', [
                    'groups' => ['quiz:question:read'],
                ]);

                return $this->render('quiz/play_classic_ia.html.twig', [
                    'questions'     => $questionsArray,
                    'quizSessionId' => $quizSession->getId(),
                ]);
                // phpcs:disable PSR12.Operators.OperatorSpacing
            } catch (InvalidQuizThemeException|AIQuizGenerationException $e) {
                // phpcs:enable
                $this->addFlash('error', $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur inattendue est survenue. Veuillez réessayer.');
            }

            return $this->redirectToRoute('app_quiz_ai');
        }

        return $this->render('quiz/ai_quiz.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
