<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__ . '/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

$em = $container->get('doctrine')->getManager();

echo "=== Analyse des Questions et Réponses ===\n\n";

// 1. Vérifier le nombre total de questions
$totalQuestions = $em->createQuery('SELECT COUNT(q) FROM App\Entity\Question q WHERE q.deletedAt IS NULL')
    ->getSingleScalarResult();
echo "📊 Total questions actives: $totalQuestions\n\n";

// 2. Vérifier le nombre de réponses par question
$questionsWithAnswers = $em->createQuery('
    SELECT q.id, q.content, COUNT(a.id) as answerCount
    FROM App\Entity\Question q
    LEFT JOIN q.quizSessionAnswers a
    WHERE q.deletedAt IS NULL
    AND (a.deletedAt IS NULL OR a.deletedAt IS NOT NULL)
    GROUP BY q.id
    ORDER BY answerCount DESC
')->setMaxResults(10)->getResult();

echo "📋 Top 10 questions avec le plus de réponses:\n";
foreach ($questionsWithAnswers as $q) {
    $content = strlen($q['content']) > 50 ? substr($q['content'], 0, 50) . '...' : $q['content'];
    echo sprintf("  - Q%d: %s (Réponses: %d)\n", $q['id'], $content, $q['answerCount']);
}

// 3. Vérifier les questions avec >= 10 réponses
$questionsWithMin10 = $em->createQuery('
    SELECT q.id, COUNT(a.id) as answerCount,
           SUM(CASE WHEN a.isCorrect = true THEN 1 ELSE 0 END) as correctCount,
           SUM(CASE WHEN a.isCorrect = false THEN 1 ELSE 0 END) as wrongCount
    FROM App\Entity\Question q
    LEFT JOIN q.quizSessionAnswers a WITH a.deletedAt IS NULL
    WHERE q.deletedAt IS NULL
    GROUP BY q.id
    HAVING COUNT(a.id) >= 10
    ORDER BY answerCount DESC
')->getResult();

echo "\n\n📈 Questions avec >= 10 réponses: " . count($questionsWithMin10) . "\n";

if (count($questionsWithMin10) > 0) {
    echo "\nDétails (5 premières):\n";
    foreach (array_slice($questionsWithMin10, 0, 5) as $q) {
        $successRate = $q['answerCount'] > 0 ? ($q['correctCount'] / $q['answerCount'] * 100) : 0;
        $failureRate = 100 - $successRate;
        echo sprintf(
            "  - Q%d: %d réponses | ✅ %d correct | ❌ %d faux | Taux réussite: %.1f%% | Taux échec: %.1f%%\n",
            $q['id'],
            $q['answerCount'],
            $q['correctCount'],
            $q['wrongCount'],
            $successRate,
            $failureRate
        );
    }
}

// 4. Tester la requête getHardestQuestions
echo "\n\n=== Test getHardestQuestions() ===\n";
try {
    $repository = $em->getRepository(App\Entity\Question::class);
    $hardest    = $repository->getHardestQuestions(5);

    if (empty($hardest)) {
        echo "❌ Aucune question retournée par getHardestQuestions()\n";

        // Test de la requête sans le HAVING
        echo "\n🔍 Test sans la condition HAVING >= 10:\n";
        $testQuery = $em->createQueryBuilder()
            ->select('q, c.name as categoryName, COUNT(a.id) as totalAnswers')
            ->from(App\Entity\Question::class, 'q')
            ->leftJoin('q.quizSessionAnswers', 'a')
            ->leftJoin('q.category', 'c')
            ->where('a.deletedAt IS NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->groupBy('q.id, c.id')
            ->orderBy('totalAnswers', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        echo 'Résultats: ' . count($testQuery) . " questions\n";
        foreach ($testQuery as $item) {
            echo sprintf("  - Q avec %d réponses\n", $item['totalAnswers']);
        }
    } else {
        echo '✅ Questions difficiles trouvées: ' . count($hardest) . "\n";
        foreach ($hardest as $item) {
            echo sprintf(
                "  - Taux échec: %.1f%% (%d réponses)\n",
                $item['failureRate'],
                $item['totalAnswers']
            );
        }
    }
} catch (Exception $e) {
    echo '❌ Erreur: ' . $e->getMessage() . "\n";
}

// 5. Tester la requête getEasiestQuestions
echo "\n\n=== Test getEasiestQuestions() ===\n";
try {
    $easiest = $repository->getEasiestQuestions(5);

    if (empty($easiest)) {
        echo "❌ Aucune question retournée par getEasiestQuestions()\n";
    } else {
        echo '✅ Questions faciles trouvées: ' . count($easiest) . "\n";
        foreach ($easiest as $item) {
            echo sprintf(
                "  - Taux réussite: %.1f%% (%d réponses)\n",
                $item['successRate'],
                $item['totalAnswers']
            );
        }
    }
} catch (Exception $e) {
    echo '❌ Erreur: ' . $e->getMessage() . "\n";
}

echo "\n=== Fin de l'analyse ===\n";
