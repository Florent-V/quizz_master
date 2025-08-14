<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Category;
use App\Entity\Difficulty;
use App\Entity\User;
use App\Enum\GameMode;
use App\Form\QuizConfigurationFormType;
use App\Repository\DifficultyRepository;
use App\Repository\QuestionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;

#[AsLiveComponent]
final class QuizConfigurationComponent extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;
    use ValidatableComponentTrait;

    #[LiveProp(writable: true, onUpdated: 'onCategoryUpdated')]
    public ?Category $category = null;

    #[LiveProp(writable: true)]
    public ?Category $subCategory = null;

    /**
     * @var int[]
     */
    #[LiveProp(writable: true)]
    #[Assert\Expression(
        'this.getTotalAvailableQuestions() > 20',
        message: 'Vous devez  avoir plus de 20 questions !',
    )]
    public array $difficulties = [];

    #[LiveProp(writable: true)]
    public ?GameMode $gameMode = null;

    #[LiveProp(writable: true)]
    #[Assert\NotBlank(message: 'Veuillez saisir un pseudo.')]
    #[Assert\Length(
        min: 3,
        max: 20,
        minMessage: 'Le pseudo doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le pseudo ne peut pas dépasser {{ limit }} caractères.',
    )]
    public ?string $pseudo = null;

    private ?User $user;

    public function __construct(
        private readonly QuestionRepository $questionRepository,
        private readonly DifficultyRepository $difficultyRepository,
        private readonly RequestStack $requestStack,
        Security $security,
    ) {
        /**
         * @var ?User $user
         */
        $user       = $security->getUser();
        $this->user = $user;
    }

    public function mount(): void
    {
        if (
            $this->user
            && null === $this->pseudo
        ) {
            /** @var User $user */
            $user         = $this->user;
            $this->pseudo = $user->getUserName();
        }
    }

    public function onCategoryUpdated(): void
    {
        if (
            null !== $this->subCategory
            && $this->category
            && $this->subCategory->getParent()?->getId() === $this->category->getId()
        ) {
            return;
        }
        $this->subCategory = null;
    }

    public function getTotalAvailableQuestions(): int
    {
        $difficultyCounts = $this->questionRepository->getAvailableDifficultyCounts(
            $this->category,
            $this->subCategory
        );

        // Si aucune difficulté n'est sélectionnée, compter toutes les questions
        if (empty($this->difficulties)) {
            return array_sum($difficultyCounts);
        }

        $total = 0;
        foreach ($this->difficulties as $difficultyId) {
            $total += $difficultyCounts[$difficultyId] ?? 0;
        }

        return $total;
    }

    public function getDifficultyQuestionCount(Difficulty $difficulty): int
    {
        $difficultyCounts = $this->questionRepository->getAvailableDifficultyCounts(
            $this->category,
            $this->subCategory
        );

        return $difficultyCounts[$difficulty->getId()] ?? 0;
    }

    public function hasSubCategories(): bool
    {
        if (null === $this->category) {
            return false;
        }

        return count($this->category->getActiveChildren()) > 0;
    }

    /**
     * @return array{}|array{
     *     category: string,
     *     subCategory: string,
     *     difficulties: string,
     *     gameMode: string,
     *     availableQuestions: int,
     *     pseudo?: string
     * }
     */
    public function getConfigurationSummary(): array
    {
        // Afficher le résumé si on a au moins un mode de jeu
        if (!$this->gameMode) {
            return [];
        }

        // Récupérer les labels des difficultés
        $difficultiesLabel = 'Toutes les difficultés';
        if (!empty($this->difficulties)) {
            // Si ce sont des IDs → on va chercher les entités

            $difficultyEntities = $this->difficultyRepository->findBy([
                'id' => $this->difficulties,
            ]);

            $difficultiesLabel = implode(', ', array_map(
                static fn (Difficulty $difficulty): string => $difficulty->getName(),
                $difficultyEntities
            ));
        }

        $summary = [
            'category'           => $this->category?->getName()    ?? 'Toutes les catégories',
            'subCategory'        => $this->subCategory?->getName() ?? 'Toutes les sous-catégories',
            'difficulties'       => $difficultiesLabel,
            'gameMode'           => $this->gameMode->getLabel(),
            'availableQuestions' => $this->getTotalAvailableQuestions(),
        ];

        if ($this->pseudo) {
            $summary['pseudo'] = $this->pseudo;
        }

        return $summary;
    }

    public function isFormValid(): bool
    {
        if (!$this->gameMode) {
            return false;
        }

        // If user is not logged in, pseudo cannot be empty
        if (null === $this->user && empty($this->pseudo)) {
            return false;
        }

        return $this->getTotalAvailableQuestions() >= 20;
    }

    #[LiveAction]
    public function startQuiz(): Response
    {
        $this->validate();
        if (!$this->isFormValid()) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        $quizConfiguration = [
            'category_id'    => $this->category?->getId(),
            'subcategory_id' => $this->subCategory?->getId(),
            'difficulty_ids' => $this->difficulties,
            'gameMode'       => $this->gameMode->value,
            'pseudo'         => $this->pseudo,
        ];

        $this->requestStack->getSession()->set('quiz_configuration', $quizConfiguration);

        return $this->redirectToRoute('app_quiz_play');
    }

    /**
     * @return FormInterface<array{
     *     category: Category|null,
     *     subCategory: Category|null,
     *     difficulties: list<Difficulty>,
     *     gameMode: GameMode|null,
     *     pseudo: string|null
     * }>
     */
    protected function instantiateForm(): FormInterface
    {
        // Préparer les sous-catégories selon la catégorie sélectionnée
        $subCategories = [];
        if (null !== $this->category) {
            $subCategories = $this->category->getActiveChildren()->toArray();
        }

        // Préparer les compteurs de difficultés
        $difficultyCounts = $this->questionRepository->getAvailableDifficultyCounts(
            $this->category,
            $this->subCategory
        );

        $difficultyEntities = [];
        if (!empty($this->difficulties)) {
            $difficultyEntities = $this->difficultyRepository->findBy([
                'id' => $this->difficulties,
            ]);
        }

        // @phpstan-ignore-next-line
        return $this->createForm(QuizConfigurationFormType::class, [
            'category'     => $this->category,
            'subCategory'  => $this->subCategory,
            'difficulties' => $difficultyEntities,
            'gameMode'     => $this->gameMode,
            'pseudo'       => $this->pseudo,
        ], [
            'subCategories'    => $subCategories,
            'difficultyCounts' => $difficultyCounts,
            'is_logged_in'     => null !== $this->user,
        ]);
    }
}
