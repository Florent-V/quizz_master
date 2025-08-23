<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Quiz\Service\QuizConfigurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
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
        RequestStack $requestStack,
        QuizConfigurationService $quizConfigService,
    ): Response {
        $session = $requestStack->getSession();

        // Récupérer la configuration si elle existe sans la supprimer dans la session
        $quizDto = $quizConfigService
            ->fromSession($session)
            // ->clearSession($session)
            ->build();


        return $this->render('quiz/configure.html.twig', [
            'quizConfiguration' => $quizDto,
            'currentStep'       => 1,
        ]);
    }
}
