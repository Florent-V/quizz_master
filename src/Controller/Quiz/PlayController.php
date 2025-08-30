<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Quiz\Exception\InvalidQuizConfigurationException;
use App\Quiz\Service\SessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

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
                default        => throw new \RuntimeException('Unsupported game mode'),
            });
        } catch (InvalidQuizConfigurationException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_quiz_configure');
        } catch (ExceptionInterface $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_home');
        }
    }
}
