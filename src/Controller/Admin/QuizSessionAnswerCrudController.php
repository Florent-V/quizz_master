<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\QuizSessionAnswer;
use App\Service\Admin\QuizSessionAnswerFieldsConfigurationService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractCrudController<QuizSessionAnswer>
 */
class QuizSessionAnswerCrudController extends AbstractCrudController
{
    use AdminCrudControllerTrait;

    public function __construct(
        private readonly QuizSessionAnswerFieldsConfigurationService $fieldsService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return QuizSessionAnswer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->configureCommonCrud($crud, 'Réponse de session', 'Réponses de session')
            ->setSearchFields(['question.content', 'proposal.content', 'quizSession.id'])
            ->setDefaultSort(['askedAt' => 'DESC'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        // Keep only the detail view and the default index.
        return $this->configureCommonActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(
                ChoiceFilter::new('isCorrect', 'Réponse Correcte')
                    ->setChoices([
                        'Oui'    => true,
                        'Non'    => false,
                        'Aucune' => null,
                    ])
            )
            ->add(
                EntityFilter::new('question', 'Question')
                ->setFormTypeOption('required', false)
            )
            ->add(
                NumericFilter::new('score', 'Score')
                ->setFormTypeOption('required', false)
            )
            ->add(
                NumericFilter::new('time', 'Temps (sec)')
                ->setFormTypeOption('required', false)
            )
            ->add(
                DateTimeFilter::new('answeredAt', 'Date de Réponse')
                ->setFormTypeOption('required', false)
            )
            ->add(
                DateTimeFilter::new('askedAt', 'Date de la Question')
                ->setFormTypeOption('required', false)
            )
            ->add(
                EntityFilter::new('quizSession', 'Session de Quiz')
                ->setFormTypeOption('required', false)
            )
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return $this->fieldsService->getFieldsForPage($pageName);
    }

    /**
     * Crée une redirection vers la page d'index du contrôleur courant.
     */
    public function redirectToIndexTemp(): Response
    {
        return $this->redirect($this->adminUrlGenerator
            ->setController(static::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }
}
