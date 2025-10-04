<?php

declare(strict_types=1);

namespace App\Quiz\Service\Import;

class QuizStructureService
{
    // Ce service gérera la structuration des données du quiz

    /**
     * Structures quiz questions by level and locale.
     *
     * @param array<string, array<string, array<int, array{
     *     id: int|string,
     *     question: string,
     *     propositions: array<int, string>,
     *     réponse: string,
     *     anecdote?: string
     * }>>> $quizzData
     *
     * @return array<string, array<string, array<string, array{
     *     id: int|string,
     *     question: string,
     *     propositions: array<int, string>,
     *     réponse: string,
     *     anecdote?: string
     * }>>>
     */
    public function structureQuestionsByLevelAndLocale(array $quizzData): array
    {
        $structuredQuestions = [];
        foreach ($quizzData as $locale => $levels) {
            foreach ($levels as $levelName => $questionsData) {
                foreach ($questionsData as $questionItem) {
                    $questionId = $questionItem['id'];
                    if (!isset($structuredQuestions[$levelName][$questionId])) {
                        $structuredQuestions[$levelName][$questionId] = [];
                    }
                    $structuredQuestions[$levelName][$questionId][$locale] = $questionItem;
                }
            }
        }

        return $structuredQuestions;
    }
}
