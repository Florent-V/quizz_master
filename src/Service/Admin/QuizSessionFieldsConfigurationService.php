<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Controller\Admin\QuizSessionAnswerCrudController;
use App\Controller\Admin\UserCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class QuizSessionFieldsConfigurationService extends AbstractFieldsConfigurationService
{
    protected function buildIndexFields(): array
    {
        return [
            $this->createIdField(),
            AssociationField::new('user'),
            IntegerField::new('score'),
            DateTimeField::new('startedAt'),
            DateTimeField::new('finishedAt'),
            $this->createdAtField(),
        ];
    }

    protected function buildDetailFields(): array
    {
        return [
            FormField::addPanel('Session Information')->collapsible(),
            AssociationField::new('user')
                ->setCrudController(UserCrudController::class),
            IntegerField::new('score'),
            DateTimeField::new('startedAt')->setFormat('dd/MM/yyyy HH:mm:ss'),
            DateTimeField::new('finishedAt')->setFormat('dd/MM/yyyy HH:mm:ss'),

            FormField::addPanel('Answers')->collapsible(),
            CollectionField::new('quizSessionAnswers', 'Answers')
                ->useEntryCrudForm(QuizSessionAnswerCrudController::class)
                ->setTemplatePath('admin/field/quiz_session_answers.html.twig'),

            FormField::addPanel('Metadata')->collapsible(),
            $this->createdAtField(),
            $this->updatedAtField(),
        ];
    }

    protected function buildFormFields(?AdminContext $context = null): array
    {
        // Sessions are not meant to be created or edited from the admin panel.
        return [];
    }
}
