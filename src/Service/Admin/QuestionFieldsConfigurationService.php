<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Controller\Admin\CategoryCrudController;
use App\Controller\Admin\DifficultyCrudController;
use App\Controller\Admin\ProposalCrudController;
use App\Entity\Question;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class QuestionFieldsConfigurationService extends AbstractFieldsConfigurationService
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
            $this->createAssociationField('category', 'Catégorie'),
            $this->createAssociationField('difficulty', 'Difficulté'),
            $this->createProposalCountField(),
            $this->createStatusField(),
            $this->createDateTimeField('createdAt', 'Créé le'),
        ];
    }

    /**
     * @return FieldInterface[]
     */
    protected function buildDetailFields(): array
    {
        return [
            FormField::addPanel('Détails de la Question'),
            $this->createTextField('content', 'Contenu'),
            $this->createTextAreaField('explanation', 'Explication'),
            $this->createTextField('hint', 'Indice'),

            FormField::addPanel('Média'),
            $this->createImageField('imageName', 'Image', '/uploads/images/questions'),

            FormField::addPanel('Classification'),
            $this->createAssociationField('category', 'Catégorie'),
            $this->createAssociationField('difficulty', 'Difficulté'),

            FormField::addPanel('Propositions'),
            IntegerField::new('proposalsCount', 'Nombre de propositions'),
            IntegerField::new('correctProposalsCount', 'Propositions correctes'),
            CollectionField::new('proposals', 'Propositions')
                ->setTemplatePath('admin/question/proposals_detail.html.twig')
                ->onlyOnDetail(),

            FormField::addPanel('Statistiques'),
            IntegerField::new('totalAnswersCount', 'Réponses totales'),
            TextField::new('correctAnswersPercentage', 'Taux de réussite'),

            FormField::addPanel('Métadonnées'),
            $this->createDateTimeField('createdAt', 'Créé le'),
            $this->createDateTimeField('updatedAt', 'Mis à jour le'),
            $this->createDateTimeField('deletedAt', 'Supprimée le'),
            AssociationField::new('createdBy', 'Créée par'),
            AssociationField::new('updatedBy', 'Modifiée par'),
        ];
    }

    /**
     * @return FieldInterface[]
     */
    protected function buildFormFields(?AdminContext $context = null): array
    {
        return [
            FormField::addPanel('Détails de la Question'),
            TextareaField::new('content', 'Contenu')
                ->setFormTypeOption('attr', ['data-ea-translatable' => 'true'])
                ->setHelp('Le contenu de la question.')
                ->setNumOfRows(3),
            TextareaField::new('explanation', 'Explication')
                ->setFormTypeOption('attr', ['data-ea-translatable' => 'true'])
                ->setHelp('Explication de la réponse, affichée après que l\'utilisateur a répondu.')
                ->setNumOfRows(3),
            TextField::new('hint', 'Indice')
                ->setHelp('Indice optionnel pour l\'utilisateur.'),
            $this->createImageUploadField('imageFile', 'Image'),

            FormField::addPanel('Associations'),
            AssociationField::new('category', 'Catégorie')
                ->setCrudController(CategoryCrudController::class),
            AssociationField::new('difficulty', 'Difficulté')
                ->setCrudController(DifficultyCrudController::class),

            CollectionField::new('proposals', 'Propositions')
                ->useEntryCrudForm(ProposalCrudController::class)
                ->setHelp('Ajoutez au moins deux propositions et marquez-en une comme correcte.'),
        ];
    }

    private function createProposalCountField(): Field
    {
        return Field::new('proposalsCount', 'Nb Propositions')
            ->setTemplatePath('admin/question/proposal_count_badge.html.twig')
            ->setSortable(false);
    }

    private function isImageField(): TextField
    {
        return TextField::new('imageName', 'Image')
            ->formatValue(function ($value, Question $question) {
                return empty($question->getImageName()) ? '🚫' : '✅';
            })
            ->setSortable(false);
    }
}
