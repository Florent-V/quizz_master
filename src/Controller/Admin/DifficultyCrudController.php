<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Difficulty;
use App\Repository\DifficultyRepository;
use App\Service\Admin\DifficultyFieldsConfigurationService;
use App\Service\DifficultyService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NullFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * @extends AbstractCrudController<Difficulty>
 */
class DifficultyCrudController extends AbstractCrudController
{
    use AdminCrudControllerTrait;

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly DifficultyService $difficultyService,
        private readonly DifficultyRepository $difficultyRepository,
        private readonly DifficultyFieldsConfigurationService $fieldsService,
        private readonly TranslatorInterface $translator,
        private readonly ChartBuilderInterface $chartBuilder,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Difficulty::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->configureCommonCrud($crud, 'Difficulté', 'Difficultés')
            ->setPageTitle(
                'edit',
                fn (Difficulty $difficulty) => sprintf('Modifier « %s »', $difficulty->getName())
            )
            ->setPageTitle(
                'detail',
                fn (Difficulty $difficulty) => sprintf('Détails de « %s »', $difficulty->getName())
            )
            ->setSearchFields(['name', 'level'])
            ->setDefaultSort(['level' => 'ASC'])
            ->setHelp('index', $this->getIndexHelp())
            ->setHelp('new', 'Créez une nouvelle difficulté')
            ->setHelp('edit', 'Modifiez les informations de la difficulté.')
            ->setHelp('detail', 'Vue détaillée de la difficulté.')
            ->addFormTheme('@A2lixTranslationForm/bootstrap_5_layout.html.twig')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $duplicateAction   = $this->buildDuplicateAction();
        $statsAction       = $this->buildStatsAction();
        $globalStatsAction = $this->buildGlobalStatsAction();

        return $this->configureCommonActions($actions)
            // --- Page INDEX ---
            ->add(Crud::PAGE_INDEX, $duplicateAction)
            ->add(Crud::PAGE_INDEX, $statsAction)
            ->add(Crud::PAGE_INDEX, $globalStatsAction)
            ->reorder(
                Crud::PAGE_INDEX,
                [Action::EDIT, Action::DETAIL, 'duplicate', Action::DELETE, 'stats', 'globalStats']
            )
            // --- Page DETAIL ---
            ->add(Crud::PAGE_DETAIL, $duplicateAction)
            ->add(Crud::PAGE_DETAIL, $statsAction)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', 'Nom'))
            ->add(NumericFilter::new('level', 'Niveau'))
            ->add(
                NullFilter::new('deletedAt', 'Supprimé')
                    ->setChoiceLabels('Actif', 'Supprimé')
            )
            ->add(DateTimeFilter::new('createdAt', 'Date de création'))
            ->add(DateTimeFilter::new('updatedAt', 'Dernière modification'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return $this->fieldsService->getFieldsForPage($pageName, $this->getContext());
    }

    // === ACTIONS PERSONNALISÉES ===
    private function buildDuplicateAction(): Action
    {
        return Action::new('duplicate', 'Dupliquer', 'fas fa-copy')
            ->linkToCrudAction('duplicateEntity')
            ->setCssClass('btn btn-info btn-sm');
    }

    private function buildStatsAction(): Action
    {
        return Action::new('stats', 'Stats')
            ->setIcon('fas fa-chart-line')
            ->linkToCrudAction('showStats')
            ->setCssClass('btn btn-outline-warning btn-sm');
    }

    private function buildGlobalStatsAction(): Action
    {
        return Action::new('globalStats', 'Statistiques Globales')
            ->setIcon('fas fa-chart-pie')
            ->linkToCrudAction('showGlobalStats')
            ->setCssClass('btn btn-outline-info btn-sm')
            ->createAsGlobalAction();
    }

    // === MÉTHODES D'ACTION ===
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        try {
            parent::deleteEntity($entityManager, $entityInstance);
            $this->addFlash('success', 'La catégorie a été supprimée avec succès.');
        } catch (\LogicException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
    }

    public function duplicateEntity(AdminContext $context): Response
    {
        $entityId = $context->getRequest()->query->get('entityId');

        if (!$entityId) {
            $this->addFlash('danger', 'Impossible de dupliquer : ID manquant.');

            return $this->redirectToIndex($this->adminUrlGenerator);
        }

        $duplicate = $this->executeWithErrorHandling(
            fn () => $this->difficultyService->duplicate((int) $entityId),
            'Difficulté dupliquée avec succès.',
            'Erreur lors de la duplication de la difficulté.'
        );

        if ($duplicate) {
            return $this->redirectToEdit($this->adminUrlGenerator, $duplicate->getId());
        }

        return $this->redirectToIndex($this->adminUrlGenerator);
    }

    public function showStats(AdminContext $context): Response
    {
        $entityId = $context->getRequest()->query->get('entityId');

        if (!$entityId) {
            $this->addFlash('danger', 'Impossible de voir les statistiques : ID manquant.');

            return $this->redirectToIndex($this->adminUrlGenerator);
        }

        try {
            $stats = $this->difficultyRepository->getStatistics((int) $entityId);
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de la consultation des statistiques : ' . $e->getMessage());

            return $this->redirectToIndex($this->adminUrlGenerator);
        }

        return $this->render('admin/difficulty/stats.html.twig', [
            'difficulty' => $stats['difficulty'],
            'stats'      => $stats,
        ]);
    }

    public function showGlobalStats(AdminContext $context): Response
    {
        $stats = $this->difficultyRepository->getQuestionCountByDifficulty();

        $labels = array_map(fn ($stat) => $stat['name'], $stats);
        $data   = array_map(fn ($stat) => $stat['question_count'], $stats);
        $colors = array_map(fn ($stat) => $stat['color'], $stats);

        $chart = $this->chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $chart->setData([
            'labels'   => $labels,
            'datasets' => [
                [
                    'label'           => 'Questions par difficulté',
                    'backgroundColor' => $colors,
                    'borderColor'     => '#fff',
                    'data'            => $data,
                ],
            ],
        ]);

        $chart->setOptions([
            'maintainAspectRatio' => false,
        ]);

        return $this->render('admin/difficulty/global_stats.html.twig', [
            'chart' => $chart,
        ]);
    }

    private function getIndexHelp(): string
    {
        $total = $this->difficultyRepository->getTotalCount();

        return $this->translator->trans('difficulty.help.index', [
            '%total%' => $total,
        ]);
    }
}
