<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Quiz\Exception\QuizBadRequestException;
use App\Quiz\Service\SessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/quiz/play',
    name: 'app_quiz_play',
    methods: ['GET']
)]
class PlayController extends AbstractController
{
    public function __invoke(
        SessionManager $sessionManager,
    ): Response {
        try {
            $quizDto = $sessionManager->getQuizConfigurationDto();

            // Redirection dynamique en fonction du mode de jeu
            return $this->redirectToRoute(match ($quizDto->gameMode->value) {
                '20Q'          => 'app_quiz_play_classic',
                'SUDDEN_DEATH' => 'app_quiz_play_sudden_death',
                'TIME_ATTACK'  => 'app_quiz_play_time_attack',
                default        => throw new QuizBadRequestException('Unsupported game mode'),
            });
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_quiz_configure');
        }
    }
}
