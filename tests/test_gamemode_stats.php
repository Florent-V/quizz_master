<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Enum\GameMode;
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__ . '/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

$repository = $container->get('doctrine')->getRepository(App\Entity\QuizSession::class);

echo "=== Statistiques par mode de jeu ===\n\n";

foreach (GameMode::cases() as $gameMode) {
    $avgScore  = $repository->getAverageScoreByGameMode($gameMode);
    $bestScore = $repository->getBestScoreByGameMode($gameMode);

    echo sprintf(
        "Mode: %s\n  Score moyen: %.1f\n  Meilleur score: %d\n\n",
        $gameMode->getLabel(),
        $avgScore,
        $bestScore
    );
}
