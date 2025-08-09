<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\Admin\CategoryFieldsConfigurationService;
use App\Service\CategoryService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractCrudController<Category>
 */
class CategoryCrudController extends AbstractCrudController
{
    use AdminCrudControllerTrait;

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly CategoryService $categoryService,
        private readonly CategoryRepository $categoryRepository,
        private readonly CategoryFieldsConfigurationService $fieldsService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->configureCommonCrud($crud, 'Catégorie', 'Catégories')
            ->setPageTitle('edit', fn (Category $category) => sprintf('Modifier « %s »', $category->getName()))
            ->setPageTitle('detail', fn (Category $category) => sprintf('Détails de « %s »', $category->getName()))
            ->setSearchFields(['name', 'description', 'slug'])
            ->setDefaultSort(['lft' => 'ASC'])
            ->setHelp('index', $this->getIndexHelp())
            ->setHelp('new', 'Créez une nouvelle catégorie. Le slug sera généré automatiquement.')
            ->setHelp('edit', 'Modifiez les informations de la catégorie.')
            ->setHelp('detail', 'Vue détaillée de la catégorie avec toutes ses informations.')
            ->addFormTheme('@A2lixTranslationForm/bootstrap_5_layout.html.twig')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $restoreAction       = $this->buildRestoreAction();
        $exportAction        = $this->buildExportAction();
        $cleanUpAction       = $this->buildCleanUpAction();
        $duplicateAction     = $this->buildDuplicateAction();
        $viewQuestionsAction = $this->buildViewQuestionsAction();
        $statsAction         = $this->buildStatsAction();

        return $this->configureCommonActions($actions)
            // --- Page INDEX ---
            ->update(
                Crud::PAGE_INDEX,
                Action::DETAIL,
                fn (Action $action) => $action->displayIf(fn (Category $d) => null === $d->getDeletedAt())
            )
            ->update(
                Crud::PAGE_INDEX,
                Action::EDIT,
                fn (Action $action) => $action->displayIf(fn (Category $d) => null === $d->getDeletedAt())
            )
            ->add(Crud::PAGE_INDEX, $restoreAction)
            ->add(Crud::PAGE_INDEX, $duplicateAction)
            ->add(Crud::PAGE_INDEX, $viewQuestionsAction)
            ->add(Crud::PAGE_INDEX, $exportAction)
            ->add(Crud::PAGE_INDEX, $cleanUpAction)
            ->add(Crud::PAGE_INDEX, $statsAction)
            ->update(
                Crud::PAGE_INDEX,
                Action::DELETE,
                fn (Action $action) => $action->displayIf(fn (Category $cat) => null === $cat->getDeletedAt())
            )
            ->reorder(
                Crud::PAGE_INDEX,
                [Action::EDIT, Action::DETAIL, 'duplicate',
                    Action::DELETE, 'restore', 'viewQuestions', 'stats']
            )

            // --- Page DETAIL ---
            ->add(Crud::PAGE_DETAIL, $duplicateAction)
            ->add(Crud::PAGE_DETAIL, $viewQuestionsAction)
            ->add(Crud::PAGE_DETAIL, $statsAction)

            // --- Page NEW ---
            // --- Page EDIT ---
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', 'Nom'))
            ->add(TextFilter::new('slug', 'Slug'))
            ->add(EntityFilter::new('parent', 'Parent'))
            ->add(ChoiceFilter::new('lvl', 'Niveau')->setChoices([
                'Parent' => 0, 'Enfant' => 1,
            ]))
            ->add(BooleanFilter::new('deletedAt', 'Supprimé')
                ->setFormTypeOptions([
                    'expanded' => false,
                    'choices'  => [
                        'Actif'    => false,
                        'Supprimé' => true,
                    ],
                ]))
            ->add(DateTimeFilter::new('createdAt', 'Date de création'))
            ->add(DateTimeFilter::new('updatedAt', 'Dernière modification'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return $this->fieldsService->getFieldsForPage($pageName, $this->getContext());
    }

    // === ACTIONS PERSONNALISÉES ===
    private function buildRestoreAction(): Action
    {
        return Action::new('restore', 'Restaurer', 'fas fa-undo')
            ->linkToCrudAction('restoreEntity')
            ->displayIf(fn (Category $cat) => null !== $cat->getDeletedAt());
    }

    private function buildDuplicateAction(): Action
    {
        return Action::new('duplicate', 'Dupliquer', 'fas fa-copy')
            ->linkToCrudAction('duplicateEntity')
            ->displayIf(fn (Category $cat) => null === $cat->getDeletedAt());
    }

    private function buildViewQuestionsAction(): Action
    {
        return Action::new('viewQuestions', 'Questions', 'fas fa-question-circle')
            ->linkToUrl(function (Category $cat) {
                return $this->adminUrlGenerator
                    ->setController('App\Controller\Admin\QuestionCrudController')
                    ->setAction(Action::INDEX)
                    ->set('filters[category][comparison]', '=')
                    ->set('filters[category][value]', $cat->getId())
                    ->generateUrl();
            })
            ->setCssClass('btn btn-primary btn-sm')
            ->displayIf(fn (Category $cat) => $cat->getTotalQuestionsCount() > 0)
        ;
    }

    private function buildExportAction(): Action
    {
        return Action::new('export', 'Exporter', 'fas fa-download')
            ->linkToCrudAction('exportCategories')
            ->setCssClass('btn btn-secondary')
            ->createAsGlobalAction();
    }

    private function buildCleanUpAction(): Action
    {
        return Action::new('cleanup', 'Nettoyer', 'fa-solid fa-snowplow')
            ->linkToCrudAction('cleanupEntity')
            ->setCssClass('btn btn-secondary')
            ->createAsGlobalAction();
    }

    private function buildStatsAction(): Action
    {
        return Action::new('stats', 'Stats')
            ->setIcon('fas fa-chart-line')
            ->linkToCrudAction('showStats')
            ->setCssClass('btn btn-outline-warning btn-sm')
            ->displayIf(fn (Category $cat) => null === $cat->getDeletedAt());
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        try {
            parent::deleteEntity($entityManager, $entityInstance);
            $this->addFlash('success', 'La catégorie a été supprimée avec succès.');
        } catch (\LogicException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
    }

    // === MÉTHODES D'ACTION ===
    public function restoreEntity(AdminContext $context): Response
    {
        // $category = $this->getEntityFromContext($context, $em, Category::class);
        $entityId = $context->getRequest()->query->get('entityId');

        if (!$entityId) {
            $this->addFlash('danger', 'Impossible de restaurer : ID manquant.');

            return $this->redirectToIndex();
        }

        $this->executeWithErrorHandling(
            fn () => $this->categoryService->restore((int) $entityId),
            'La catégorie a été restaurée avec succès.',
            'Erreur lors de la restauration de la catégorie.'
        );

        return $this->redirectToIndex();
    }

    public function duplicateEntity(AdminContext $context): Response
    {
        $entityId = $context->getRequest()->query->get('entityId');

        if (!$entityId) {
            $this->addFlash('danger', 'Impossible de voir les statistiques : ID manquant.');

            return $this->redirectToIndex();
        }

        $duplicate = $this->executeWithErrorHandling(
            fn () => $this->categoryService->duplicate((int) $entityId),
            'Catégorie dupliquée avec succès.',
            'Erreur lors de la duplication de la catégorie.'
        );

        if ($duplicate) {
            return $this->redirectToEdit($duplicate->getId());
        }

        return $this->redirectToIndex();
    }

    public function cleanupEntity(AdminContext $context, EntityManagerInterface $em): Response
    {
        return $this->executeWithErrorHandling(
            function () {
                $results = $this->categoryService->cleanupCategories();
                $message = sprintf(
                    'Nettoyage terminé : %d orphelines corrigées, %d doublons fusionnés.',
                    $results['orphaned_fixed'],
                    $results['duplicates_merged']
                );
                $this->addInfoFlash($message);

                return $this->redirectToIndex();
            },
            'Nettoyage des catégories effectué.',
            'Erreur lors du nettoyage des catégories.'
        );
    }

    public function exportCategories(AdminContext $context): Response
    {
        return $this->executeWithErrorHandling(
            fn () => $this->generateCsvResponse(),
            'Export réalisé avec succès.',
            'Erreur lors de l\'export des catégories.'
        );
    }

    public function showStats(AdminContext $context): Response
    {
        $entityId = $context->getRequest()->query->get('entityId');

        if (!$entityId) {
            $this->addFlash('danger', 'Impossible de voir les statistiques : ID manquant.');

            return $this->redirectToIndex();
        }

        try {
            $stats = $this->categoryRepository->getStatistics((int) $entityId);
            $this->addFlash('success', 'Statistiques affichées avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de la consultation des statistiques : ' . $e->getMessage());

            return $this->redirectToIndex();
        }

        return $this->render('admin/category/stats.html.twig', [
            'category' => $stats['category'],
            'stats'    => $stats,
        ]);
    }

    private function generateCsvResponse(): Response
    {
        $data = $this->categoryRepository->exportToArray();

        $tempFile = tmpfile();
        if (!empty($data)) {
            $firstRow = reset($data);
            if (false !== $firstRow) {
                fputcsv($tempFile, array_keys($firstRow));
                foreach ($data as $row) {
                    fputcsv($tempFile, $row);
                }
            }
        }

        rewind($tempFile);
        $csv = stream_get_contents($tempFile);
        fclose($tempFile);

        return new Response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="categories.csv"',
        ]);
    }

    private function getIndexHelp(): string
    {
        $totalCategories   = $this->categoryRepository->getTotalCount();
        $deletedCategories = $this->categoryRepository->getDeletedCount();

        return sprintf(
            'Gérez vos catégories de questions (%d catégories dont %d supprimées). ' .
            'Les catégories sont organisées en arborescence pour une meilleure organisation. ' .
            'Structure hiérarchique : Parent → Enfant (2 niveaux max)',
            $totalCategories,
            $deletedCategories
        );
    }
}
