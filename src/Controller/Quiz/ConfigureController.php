<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Enum\GameMode;
use App\Quiz\Exception\InvalidSessionException;
use App\Quiz\Service\QuizConfigurationService;
use App\Quiz\Service\SessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/quiz/configure',
    name: 'app_quiz_configure',
    methods: ['GET']
)]
class ConfigureController extends AbstractController
{
    public function __invoke(
        SessionManager $sessionManager,
        QuizConfigurationService $quizConfigurationService,
    ): Response {
        $quizDto = null;
        try {
            $quizDto = $sessionManager->getQuizConfigurationDto();
            $quizDto = $quizConfigurationService->retrieveData($quizDto);
        } catch (InvalidSessionException) {
            // on laisse $quizDto à null
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->render('quiz/configure.html.twig', [
            'quizConfiguration' => $quizDto,
            'currentStep'       => 1,
            'gameModes'         => GameMode::cases(),
        ]);
    }
}
