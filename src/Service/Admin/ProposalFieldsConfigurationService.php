<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Controller\Admin\CategoryCrudController;
use App\Controller\Admin\DifficultyCrudController;
use App\Entity\Proposal;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ProposalFieldsConfigurationService extends AbstractFieldsConfigurationService
{
    // === CHAMPS PAR PAGE ===
    /**
     * @return FieldInterface[]
     */
    protected function buildIndexFields(): array
    {
        return [
            $this->createTextField('content', 'Contenu'),
            $this->isImageField(),
            $this->creationQuestionField(),
            $this->createStatusField(),
            $this->createBooleanField('isCorrect', 'Correcte')
                ->setDisabled(true),
        ];
    }

    /**
     * @return FieldInterface[]
     */
    protected function buildDetailFields(): array
    {
        return [
            FormField::addPanel('Détails de la Proposition'),
            $this->createTextField('content', 'Contenu'),
            $this->createBooleanField('isCorrect', 'Correcte'),

            FormField::addPanel('Média'),
            $this->createImageField('imageName', 'Image', '/uploads/images/proposals'),

            FormField::addPanel('Question associée'),
            $this->createAssociationField('question', 'Question'),
            $this->createQuestionCategoryField(),
            $this->createQuestionDifficultyField(),

            FormField::addPanel('Statistiques'),
            $this->createAnswersCountField(),
            $this->createSelectionPercentageField(),

            FormField::addPanel('Métadonnées'),
            $this->createDateTimeField('createdAt', 'Créé le'),
            $this->createDateTimeField('updatedAt', 'Mis à jour le'),
            $this->createDateTimeField('deletedAt', 'Supprimée le'),
            $this->createdByField(),
            $this->updatedByField(),
        ];
    }

    /**
     * @return FieldInterface[]
     */
    protected function buildFormFields(?AdminContext $context = null): array
    {
        return [
            FormField::addPanel('Détails de la Proposition'),
            TextareaField::new('content', 'Contenu')
                ->setFormTypeOption('attr', ['data-ea-translatable' => 'true'])
                ->setHelp('Le contenu de la proposition.')
                ->setNumOfRows(2),
            $this->createBooleanField('isCorrect', 'Correcte')
                ->setHelp('Cochez si cette proposition est la bonne réponse.'),
            $this->createImageUploadField('imageFile', 'Image'),

            FormField::addPanel('Question associée'),
            $this->createQuestionFormField(),
        ];
    }

    private function isImageField(): TextField
    {
        return TextField::new('imageName', 'Image')
            ->formatValue(function ($value, Proposal $proposal) {
                return empty($proposal->getImageName()) ? '🚫' : '✅';
            })
            ->setSortable(false);
    }

    private function creationQuestionField(): AssociationField
    {
        return AssociationField::new('question', 'Question')
            ->formatValue(function ($value, Proposal $proposal) {
                $question = $proposal->getQuestion();
                if (!$question) {
                    return '';
                }

                return substr($question->getContent(), 0, 80) .
                    (strlen($question->getContent()) > 80 ? '...' : '');
            })
            ->onlyOnIndex();
    }

    private function createQuestionCategoryField(): AssociationField
    {
        return AssociationField::new('question.category', 'Catégorie de la question')
            ->setCrudController(CategoryCrudController::class)
            ->onlyOnDetail();
    }

    private function createQuestionDifficultyField(): AssociationField
    {
        return AssociationField::new('question.difficulty', 'Difficulté de la question')
            ->setCrudController(DifficultyCrudController::class)
            ->onlyOnDetail();
    }

    private function createSelectionPercentageField(): TextField
    {
        return TextField::new('selectionPercentage', 'Taux de sélection')
            ->formatValue(function ($value, Proposal $proposal) {
                $questionTotal = $proposal->getQuestion()?->getQuizSessionAnswers()->count() ?? 0;
                if (0 === $questionTotal) {
                    return 'Aucune réponse';
                }

                $proposalCount = $proposal->getQuizSessionAnswers()->count();

                return round(($proposalCount / $questionTotal) * 100, 2) . '%';
            });
    }

    private function createAnswersCountField(): IntegerField
    {
        return IntegerField::new('answersCount', 'Nombre de fois sélectionnée')
            ->formatValue(function ($value, Proposal $proposal) {
                return $proposal->getQuizSessionAnswers()->count();
            });
    }

    private function createQuestionFormField(): AssociationField
    {
        return AssociationField::new('question', 'Question')
            ->setRequired(true)
            ->autocomplete()
            ->setQueryBuilder(function ($queryBuilder) {
                return $queryBuilder
                    ->orderBy('entity.createdAt', 'DESC')
                    ->setMaxResults(5);
            })
            ->formatValue(function ($value, Proposal $proposal) {
                $question = $proposal->getQuestion();
                if (!$question) {
                    return '';
                }

                return substr($question->getContent(), 0, 100) .
                    (strlen($question->getContent()) > 100 ? '...' : '');
            })
            ->onlyOnForms();
    }
}
