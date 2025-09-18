<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\QuizSessionAnswer;
use App\Service\Admin\QuizSessionAnswerFieldsConfigurationService;
use App\Service\QuizSessionAnswerService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

/**
 * @extends AbstractCrudController<QuizSessionAnswer>
 */
class QuizSessionAnswerCrudController extends AbstractCrudController
{
    use AdminCrudControllerTrait;

    public function __construct(
        private readonly QuizSessionAnswerFieldsConfigurationService $fieldsService,
        // @phpstan-ignore-next-line
        private readonly AdminUrlGenerator $adminUrlGenerator,
        // private readonly QuizSessionAnswerService $quizSessionAnswerService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return QuizSessionAnswer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->configureCommonCrud($crud, 'Quiz Session Answer', 'Quiz Session Answers')
            ->setSearchFields(['question.content', 'proposal.content', 'quizSession.id'])
            ->setDefaultSort(['askedAt' => 'ASC'])
            // These records are created by the application, so read-only here.
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return $this->fieldsService->getFieldsForPage($pageName);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Keep only the detail view and the default index.
        return $this->configureCommonActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
