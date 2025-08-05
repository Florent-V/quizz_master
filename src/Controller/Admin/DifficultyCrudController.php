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
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<Difficulty>
 */
class DifficultyCrudController extends AbstractCrudController
{
    use AdminCrudControllerTrait;

    public function __construct(
        private readonly DifficultyService $difficultyService,
        private readonly DifficultyRepository $difficultyRepository,
        private readonly DifficultyFieldsConfigurationService $fieldsService,
        private readonly TranslatorInterface $translator,
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
        $restoreAction   = $this->buildRestoreAction();
        $duplicateAction = $this->buildDuplicateAction();
        $statsAction     = $this->buildStatsAction();

        return $this->configureCommonActions($actions)
            // --- Page INDEX ---
            ->add(Crud::PAGE_INDEX, $restoreAction)
            ->add(Crud::PAGE_INDEX, $duplicateAction)
            ->add(Crud::PAGE_INDEX, $statsAction)
            ->update(
                Crud::PAGE_INDEX,
                Action::DELETE,
                fn (Action $action) => $action->displayIf(fn (Difficulty $d) => null === $d->getDeletedAt())
            )
            ->reorder(
                Crud::PAGE_INDEX,
                [Action::EDIT, Action::DETAIL, 'duplicate', Action::DELETE, 'restore', 'stats']
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

    private function buildRestoreAction(): Action
    {
        return Action::new('restore', 'Restaurer', 'fas fa-undo')
            ->linkToCrudAction('restoreEntity')
            ->setCssClass('btn btn-success btn-sm')
            ->displayIf(fn (Difficulty $d) => null !== $d->getDeletedAt());
    }

    private function buildDuplicateAction(): Action
    {
        return Action::new('duplicate', 'Dupliquer', 'fas fa-copy')
            ->linkToCrudAction('duplicateEntity')
            ->setCssClass('btn btn-info btn-sm')
            ->displayIf(fn (Difficulty $d) => null === $d->getDeletedAt());
    }

    private function buildStatsAction(): Action
    {
        return Action::new('stats', 'Stats')
            ->setIcon('fas fa-chart-line')
            ->linkToCrudAction('showStats')
            ->setCssClass('btn btn-outline-warning btn-sm');
    }

    public function restoreEntity(AdminContext $context, EntityManagerInterface $em): Response
    {
        $entityId = $context->getRequest()->query->get('entityId');

        if (!$entityId) {
            $this->addFlash('danger', 'Impossible de restaurer : ID manquant.');

            return $this->redirectToIndex();
        }

        $this->executeWithErrorHandling(
            fn () => $this->difficultyService->restore((int) $entityId),
            'La difficulté a été restaurée avec succès.',
            'Erreur lors de la restauration de la difficulté.'
        );

        return $this->redirectToIndex();
    }

    public function duplicateEntity(AdminContext $context): Response
    {
        $entityId = $context->getRequest()->query->get('entityId');

        if (!$entityId) {
            $this->addFlash('danger', 'Impossible de dupliquer : ID manquant.');

            return $this->redirectToIndex();
        }

        $duplicate = $this->executeWithErrorHandling(
            fn () => $this->difficultyService->duplicate((int) $entityId),
            'Difficulté dupliquée avec succès.',
            'Erreur lors de la duplication de la difficulté.'
        );

        if ($duplicate) {
            return $this->redirectToEdit($duplicate->getId());
        }

        return $this->redirectToIndex();
    }

    public function showStats(AdminContext $context): Response
    {
        $entityId = $context->getRequest()->query->get('entityId');

        if (!$entityId) {
            $this->addFlash('danger', 'Impossible de voir les statistiques : ID manquant.');

            return $this->redirectToIndex();
        }

        try {
            $stats = $this->difficultyRepository->getStatistics((int) $entityId);
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de la consultation des statistiques : ' . $e->getMessage());

            return $this->redirectToIndex();
        }

        return $this->render('admin/difficulty/stats.html.twig', [
            'difficulty' => $stats['difficulty'],
            'stats'      => $stats,
        ]);
    }

    private function getIndexHelp(): string
    {
        $total   = $this->difficultyRepository->getTotalCount();
        $deleted = $this->difficultyRepository->getDeletedCount();

        return $this->translator->trans('difficulty.help.index', [
            '%total%'   => $total,
            '%deleted%' => $deleted,
        ]);
    }
}
