<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Quiz\Service\SessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/quiz/restart',
    name: 'app_quiz_restart',
    methods: ['GET']
)]
class RestartController extends AbstractController
{
    public function __invoke(
        SessionManager $sessionManager,
    ): Response {
        $sessionManager->clear('quiz');

        return $this->redirectToRoute('app_quiz_configure');
    }
}
