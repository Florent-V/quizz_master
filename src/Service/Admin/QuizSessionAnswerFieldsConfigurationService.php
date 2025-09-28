<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Controller\Admin\DifficultyCrudController;
use App\Controller\Admin\ProposalCrudController;
use App\Controller\Admin\QuestionCrudController;
use App\Controller\Admin\QuizSessionCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class QuizSessionAnswerFieldsConfigurationService extends AbstractFieldsConfigurationService
{
    protected function buildIndexFields(): array
    {
        return [
            $this->createIdField(),
            AssociationField::new('quizSession', 'Session de Quiz')
                ->setCrudController(QuizSessionCrudController::class),
            AssociationField::new('question', 'Question')
                ->setCrudController(QuestionCrudController::class),
            AssociationField::new('question.difficulty', 'Difficulté de la question')
                ->setCrudController(DifficultyCrudController::class),
            AssociationField::new('proposal', 'Proposition choisie')
                ->setCrudController(ProposalCrudController::class),
            BooleanField::new('isCorrect', 'Correcte')
                ->renderAsSwitch(false),
            IntegerField::new('score', 'Score'),
            IntegerField::new('time', 'Temps de réponse en millisecondes'),
        ];
    }

    protected function buildDetailFields(): array
    {
        return [
            FormField::addPanel('Answer Details')->collapsible(),
            $this->createIdField(),
            AssociationField::new('quizSession', 'Session')
                ->setCrudController(QuizSessionCrudController::class),
            AssociationField::new('question')
                ->setCrudController(QuestionCrudController::class),
            AssociationField::new('proposal', 'Chosen Proposal')
                ->setCrudController(ProposalCrudController::class)
                ->setRequired(false),
            BooleanField::new('isCorrect'),
            IntegerField::new('time', 'Time (seconds)')
                ->setHelp('Time taken to answer the question in seconds.'),

            FormField::addPanel('Metadata')->collapsible(),
            DateTimeField::new('askedAt')->setFormat('dd/MM/yyyy HH:mm:ss'),
            DateTimeField::new('answeredAt')->setFormat('dd/MM/yyyy HH:mm:ss'),
            $this->createdAtField(),
            $this->updatedAtField(),
        ];
    }

    protected function buildFormFields(?AdminContext $context = null): array
    {
        // Quiz session answers are not meant to be created or edited from the admin panel.
        return [];
    }
}
