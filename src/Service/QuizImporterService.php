<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Difficulty;
use App\Entity\Proposal;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\TranslatableListener;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class QuizImporterService
{
    public const string DEFAULT_LOCALE = 'fr';

    // Compteurs pour le résumé
    /** @var array<string, int|string[]> */
    public array $importStats = [
        'categories_created'   => 0,
        'categories_updated'   => 0,
        'questions_created'    => 0,
        'proposals_created'    => 0,
        'difficulties_created' => 0,
        'errors'               => 0,
        'error_messages'       => [],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
        private readonly LoggerInterface $logger,
        private readonly TranslatableListener $translatableListener,
    ) {
    }

    public function resetStats(): void
    {
        $this->importStats = [
            'categories_created'   => 0,
            'categories_updated'   => 0,
            'questions_created'    => 0,
            'proposals_created'    => 0,
            'difficulties_created' => 0,
            'errors'               => 0,
            'error_messages'       => [],
        ];
    }

    /** @return array<string, int|string[]> */
    public function importFromJson(string $jsonContent, string $defaultLocale = self::DEFAULT_LOCALE): array
    {
        $this->resetStats();
        $data = json_decode($jsonContent, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            $this->logger->error('Invalid JSON provided: ' . json_last_error_msg());
            ++$this->importStats['errors'];
            $this->importStats['error_messages'][] = 'Invalid JSON: ' . json_last_error_msg();

            return $this->importStats;
        }

        $this->entityManager->beginTransaction();

        try {
            $this->processQuizData($data, $defaultLocale);
            $this->entityManager->flush(); // Flush toutes les entités persistées
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Error during import: ' . $e->getMessage(), ['exception' => $e]);
            ++$this->importStats['errors'];
            $this->importStats['error_messages'][] = 'Import failed: ' . $e->getMessage();
        }

        return $this->importStats;
    }

    private function processQuizData(array $data, string $defaultLocale): void
    {
        $categoryTranslations = $data['catégorie-nom-slogan'] ?? [];
        if (empty($categoryTranslations[$defaultLocale])) {
            throw new \RuntimeException("Default locale '$defaultLocale' data not found for category.");
        }

        // --- Category Processing ---
        $mainCategoryName = $categoryTranslations[$defaultLocale]['catégorie'];
        $mainCategory     = $this->findOrCreateCategory($mainCategoryName, null); // No parent for main category

        foreach ($categoryTranslations as $locale => $translation) {
            $mainCategory->setTranslatableLocale($locale);
            if (isset($translation['catégorie'])) {
                $mainCategory->setName($translation['catégorie']);
            }
            if (isset($translation['slogan'])) {
                $mainCategory->setDescription($translation['slogan']);
            }
            $this->entityManager->persist($mainCategory);
            $this->translatableListener->setTranslatableLocale($locale);
            $this->entityManager->flush(); // Flush each translation immediately
        }

        $subCategoryName = $categoryTranslations[$defaultLocale]['nom'];
        $subCategory     = $this->findOrCreateCategory($subCategoryName, $mainCategory);

        foreach ($categoryTranslations as $locale => $translation) {
            $subCategory->setTranslatableLocale($locale);
            if (isset($translation['nom'])) {
                $subCategory->setName($translation['nom']);
            }
            $this->entityManager->persist($subCategory);
            $this->translatableListener->setTranslatableLocale($locale);
            $this->entityManager->flush(); // Flush each translation immediately
        }

        // --- End Category Processing ---

        // --- Question Processing ---
        $baseDifficultyLevel = (int) explode(' / ', $data['difficulté'])[0];

        // Pre-process questions to group all locales for each question by its ID
        $structuredQuestions = [];
        foreach ($data['quizz'] as $locale => $levels) {
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



        // 1. Create base questions and proposals for the default locale and then process translations
        $defaultLocaleQuestions = [];
        foreach ($structuredQuestions as $levelName => $questionsByLevel) {
            $difficultyEntity = $this->getDifficultyEntity($baseDifficultyLevel, $levelName);
            if (!$difficultyEntity) {
                continue;
            }

            foreach ($questionsByLevel as $questionId => $questionLocalesData) {
                // Ensure default locale data exists for this question
                if (!isset($questionLocalesData[$defaultLocale])) {
                    $this->logger->warning(
                        "Default locale data not found for question ID {$questionId} in level {$levelName}."
                    );
                    ++$this->importStats['errors'];
                    $this->importStats['error_messages'][] = "Default locale data missing for question ID {$questionId} in level {$levelName}.";
                    continue;
                }

                $questionItemDefault                                     = $questionLocalesData[$defaultLocale];
                list('question' => $question, 'proposals' => $proposals) = $this->createBaseQuestionAndProposals(
                        $questionItemDefault,
                        $subCategory,
                        $difficultyEntity,
                        $defaultLocale
                    );
                $this->entityManager->persist($question);
                $this->translatableListener->setTranslatableLocale($defaultLocale);
                $this->entityManager->flush(); // Flush after creating base question to ensure it gets an ID

                // Store the question for later reference (though not strictly needed with this approach)
                $defaultLocaleQuestions[$levelName][$questionId] = $question;

                // Process translations for other locales for this specific question
                foreach ($questionLocalesData as $locale => $questionItem) {
                    if ($locale === $defaultLocale) {
                        continue; // Skip default locale, already processed
                    }

                    $this->updateQuestionAndProposalsTranslations($question, $proposals, $questionItem, $locale);
                    $this->translatableListener->setTranslatableLocale($locale);
                    $this->entityManager->flush();
                }
            }
        }
        // --- End Question Processing ---
    }

    private function findOrCreateCategory(string $name, ?Category $parent = null): Category
    {
        $slug = (string) $this->slugger->slug($name)->lower();

        // Recherche de la catégorie par slug et parent
        $criteria = ['slug' => $slug];
        if ($parent) {
            $criteria['parent'] = $parent;
        } else {
            $criteria['parent'] = null; // Pour les catégories racines
        }

        $category = $this->entityManager->getRepository(Category::class)->findOneBy($criteria);

        if (!$category) {
            $category = new Category();
            $category->setName($name); // Le nom sera traduit ensuite
            $category->setSlug($slug);
            if ($parent) {
                $category->setParent($parent);
            }
            $this->entityManager->persist($category);
            ++$this->importStats['categories_created'];
        } else {
            ++$this->importStats['categories_updated'];
        }

        return $category;
    }

    private function getDifficultyEntity(int $baseDifficultyLevel, string $levelName): ?Difficulty
    {
        $level = $this->calculateDifficultyLevel($baseDifficultyLevel, $levelName);

        if (null === $level) {
            // Error message already logged in calculateDifficultyLevel
            return null;
        }

        if ($level < 1 || $level > 5) {
            $this->logger->warning("Calculated difficulty level out of bounds (1-5): {$level} for {$levelName}");
            $this->importStats['error_messages'][] = "Calculated difficulty level out of bounds (1-5): {$level} for {$levelName}";
            ++$this->importStats['errors'];

            return null;
        }

        $difficulty = $this->entityManager->getRepository(Difficulty::class)->findOneBy(['level' => $level]);
        if (!$difficulty) {
            $difficulty = new Difficulty();
            $difficulty->setLevel($level);
            // Pour l'instant, le nom de l'entité Difficulty n'est pas traduit dans ce service.
            // On pourrait vouloir charger des noms de difficulté prédéfinis.
            $difficulty->setName('Niveau ' . $level); // TODO: Internationalize or use predefined names
            $this->entityManager->persist($difficulty);
            ++$this->importStats['difficulties_created'];
        }

        return $difficulty;
    }

    private function calculateDifficultyLevel(int $baseDifficultyLevel, string $levelName): ?int
    {
        $level = 0;
        switch (strtolower($levelName)) {
            case 'débutant':
                $level = max(1, $baseDifficultyLevel - 1);
                break;
            case 'confirmé':
                $level = $baseDifficultyLevel;
                break;
            case 'expert':
                $level = min(5, $baseDifficultyLevel + 1);
                break;
            default: // Si le nom du niveau n'est pas reconnu
                $this->logger->warning("Unknown difficulty level name: {$levelName}");
                $this->importStats['error_messages'][] = "Unknown difficulty level name: {$levelName}";
                ++$this->importStats['errors'];

                return null;
        }

        return $level;
    }

    private function createBaseQuestionAndProposals(
        array $questionItem,
        Category $subCategory,
        Difficulty $difficultyEntity,
        string $defaultLocale,
    ): array {
        $question = new Question();
        $question->setCategory($subCategory);
        $question->setDifficulty($difficultyEntity);
        $question->setTranslatableLocale($defaultLocale);
        $question->setContent($questionItem['question']);
        if (isset($questionItem['anecdote'])) {
            $question->setExplanation($questionItem['anecdote']);
        }
        // Removed incorrect setHint as 'hint' field is not present in JSON

        $this->entityManager->persist($question);
        ++$this->importStats['questions_created'];

        $proposals = [];
        foreach ($questionItem['propositions'] as $propContent) {
            $isCorrect = ($propContent === $questionItem['réponse']);
            $proposal  = new Proposal();
            $proposal->setQuestion($question);
            $proposal->setIsCorrect($isCorrect);
            $proposal->setTranslatableLocale($defaultLocale);
            $proposal->setContent($propContent);

            $this->entityManager->persist($proposal);
            $proposals[] = $proposal;
            ++$this->importStats['proposals_created'];
        }

        return ['question' => $question, 'proposals' => $proposals];
    }

    private function updateQuestionAndProposalsTranslations(
        Question $question,
        array $proposals,
        array $questionItem,
        string $locale,
    ): void {
        $question->setTranslatableLocale($locale);
        $question->setContent($questionItem['question']);
        if (isset($questionItem['anecdote'])) {
            $question->setExplanation($questionItem['anecdote']);
        }
        // Explicitly persist the question to ensure Gedmo's TranslatableListener picks up changes
        // This is still important as it signals to Doctrine that
        // the question entity (and its relations) has been modified.
        $this->entityManager->persist($question);

        // Use the passed proposals array directly
        foreach ($questionItem['propositions'] as $propIndex => $propContent) {
            if (isset($proposals[$propIndex])) {
                $proposals[$propIndex]->setTranslatableLocale($locale);
                $proposals[$propIndex]->setContent($propContent);
                // Explicitly persist the proposal to ensure its changes are registered for the current locale
                $this->entityManager->persist($proposals[$propIndex]);
            }
        }
        // The flush happens outside this method, in the main import loop.
    }
}
