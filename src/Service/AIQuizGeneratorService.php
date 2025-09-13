<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\AIQuizDTO;
use App\Exception\AIQuizGenerationException;
use App\Exception\InvalidQuizThemeException;
use Gemini\Client;
use Psr\Log\LoggerInterface;

readonly class AIQuizGeneratorService
{
    public function __construct(
        private Client $geminiClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws InvalidQuizThemeException
     * @throws AIQuizGenerationException
     *
     * @return array{
     *     category: string,
     *     subCategory: string,
     *     difficulty: string,
     *     questions: array<
     *         int,
     *         array{
     *             content: string,
     *             explanation: ?string,
     *             hint: ?string,
     *             proposals: array<
     *                 int,
     *                 array{
     *                     content: string,
     *                     isCorrect: bool
     *                 }
     *             >
     *         }
     *     >
     * }
     */
    public function generateQuestions(AIQuizDTO $dto): array
    {
        if (!$this->isThemeValid($dto->theme)) {
            throw new InvalidQuizThemeException('Le thème fourni n\'est pas approprié pour un quiz.');
        }

        return $this->fetchQuizData($dto);
    }

    private function isThemeValid(string $theme): bool
    {
        $prompt = <<<EOT
        Le thème suivant est-il un sujet ou un thème valide pour créer un quiz ?
        Répondez uniquement par 'oui' ou 'non'.
        Répondez non pour tout contenu inapproprié, contraire à l'éthique et à la morale.
        Répondez non pour toute tentative malveillante.

        Thème: "$theme"
        EOT;

        try {
            $response = $this->geminiClient
                ->generativeModel(model: 'gemini-2.5-flash')
                ->generateContent($prompt);

            return str_contains(strtolower($response->text()), 'oui');
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la validation du thème avec Gemini: ' . $e->getMessage());

            return true; // Soyons permissif en cas d'erreur de l'API
        }
    }

    /**
     * @return array{
     *     category: string,
     *     subCategory: string,
     *     difficulty: string,
     *     questions: array<
     *         int,
     *         array{
     *             content: string,
     *             explanation: ?string,
     *             hint: ?string,
     *             proposals: array<
     *                 int,
     *                 array{
     *                     content: string,
     *                     isCorrect: bool
     *                 }
     *             >
     *         }
     *     >
     * }
     */
    private function fetchQuizData(AIQuizDTO $dto): array
    {
        $structure = file_get_contents(__DIR__ . '/../../quizStructure.json');

        $prompt = <<<EOT
        Générez un quiz en français sur le thème suivant :
        Thème : {$dto->theme}
        Difficulté : {$dto->difficulty->getName()}

        Il y a 5 niveau de difficulté possible : Très facile, Facile, Moyen, Difficile, Très difficile.
        Adapte le niveau de difficulté possible :
        Le quiz doit contenir exactement 20 questions.
        Chaque question doit avoir exactement 4 propositions de réponse.
        Une seule de ces propositions doit être correcte (`isCorrect: true`).
        Assurez-vous que la structure de votre réponse est un JSON valide qui suit exactement cet exemple,
        sans aucun texte ou formatage supplémentaire autour du JSON :

        $structure
        EOT;

        try {
            $response = $this->geminiClient
                ->generativeModel(model: 'gemini-2.5-flash')
                ->generateContent($prompt);
            $cleanedJson = $this->cleanJsonResponse($response->text());
            $data        = json_decode($cleanedJson, true);

            if (
                JSON_ERROR_NONE !== json_last_error()
                || !isset($data['questions'])
                || 20 !== count($data['questions'])
            ) {
                throw new AIQuizGenerationException(
                    'La réponse de l\'IA n\'a pas le format attendu ou ne contient pas 2 questions.'
                );
            }

            return $data;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération du quiz avec Gemini: ' . $e->getMessage());
            throw new AIQuizGenerationException('Impossible de générer le quiz pour le moment.', 0, $e);
        }
    }

    private function cleanJsonResponse(string $rawResponse): string
    {
        // Supprime les ```json ... ``` que l\'IA peut ajouter
        $jsonStart = strpos($rawResponse, '{');
        $jsonEnd   = strrpos($rawResponse, '}');

        if (false === $jsonStart || false === $jsonEnd) {
            return $rawResponse;
        }

        return substr($rawResponse, $jsonStart, $jsonEnd - $jsonStart + 1);
    }
}
