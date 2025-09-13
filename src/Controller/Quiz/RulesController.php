<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Enum\GameMode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/quiz/rules',
    name: 'app_quiz_rules',
    methods: ['GET']
)]
class RulesController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('quiz/rules.html.twig', [
            'gameModes' => GameMode::cases(),
        ]);
    }
}
