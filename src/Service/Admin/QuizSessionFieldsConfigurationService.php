<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Controller\Admin\DifficultyCrudController;
use App\Entity\QuizSession;
use App\Enum\GameMode;
use App\Enum\QuizSessionStatus;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class QuizSessionFieldsConfigurationService extends AbstractFieldsConfigurationService
{
    // === CHAMPS PAR PAGE ===
    /**
     * @return FieldInterface[]
     */
    protected function buildIndexFields(): array
    {
        return [
            $this->createUuidField(),
            $this->createPseudoField(),
            $this->createGameModeField(),
            $this->createGameStatusField(),
            $this->createCategoryField(),
            $this->createSubCategoryField(),
            $this->createDifficultiesField(),
            $this->createScoreFieldWithBadge(),
            $this->createDurationField(),
            $this->createAnswersStatsField(),
            $this->createAnswersCountField(),
            $this->createStartedAtField(),
            $this->createStatusField(),
            //            TextField::new('pseudo', 'Pseudo'),
            //            AssociationField::new('user', 'Utilisateur')
            //                ->setCrudController(UserCrudController::class),
            //            AssociationField::new('category', 'Catégorie')
            //                ->setCrudController(CategoryCrudController::class),
            //            AssociationField::new('subCategory', 'Sous-catégorie')
            //               ->setCrudController(CategoryCrudController::class),

        ];
    }

    protected function buildDetailFields(): array
    {
        return [
            FormField::addPanel('🎮 Informations de Session')->collapsible(),
            $this->createUuidField(),
            $this->createPseudoField(),
            $this->createUserField(),
            $this->createGameModeField(),
            $this->createStatusField(),

            FormField::addPanel('📊 Résultats')->collapsible(),
            $this->createScoreField(),
            $this->createDetailedAnswersStatsField(),
            $this->createDurationField(),

            FormField::addPanel('🎯 Configuration de Jeu')->collapsible(),
            $this->createCategoryField(),
            $this->createSubCategoryField(),
            $this->createDifficultiesField(),

            FormField::addPanel('📋 Réponses Détaillées')->collapsible(),
            $this->createAnswersListField(),

            FormField::addPanel('⏱️ Chronologie')->collapsible(),
            $this->createStartedAtField(),
            $this->createFinishedAtField(),

            FormField::addPanel('Metadata')->collapsible(),
            $this->createdAtField(),
            $this->updatedAtField(),
            $this->createdByField(),
            $this->updatedByField(),
            $this->deletedAtField(),
        ];
    }

    /**
     * @return FieldInterface[]
     */
    protected function buildFormFields(?AdminContext $context = null): array
    {
        // Sessions are not meant to be created or edited from the admin panel.
        return [];
    }

    // === CREATION DES CHAMPS INDIVIDUELS ===
    private function createPseudoField(): TextField
    {
        return TextField::new('pseudo', 'Joueur')
            ->setTemplatePath('admin/quiz_session/quiz_session_player.html.twig')
            ->addCssClass('fw-bold')
            ->formatValue(fn ($value, QuizSession $entity) => $entity);
    }

    private function createUserField(): AssociationField
    {
        return AssociationField::new('user', 'Utilisateur Connecté')
            ->setTemplatePath('admin/quiz_session/user_link.html.twig');
    }

    private function createGameModeField(): ChoiceField
    {
        return ChoiceField::new('gameMode', 'Mode de Jeu')
            ->setChoices(GameMode::getChoices())
            ->setTemplatePath('admin/quiz_session/game_mode_badge.html.twig')
            ->renderExpanded(false);
    }

    private function createGameStatusField(): ChoiceField
    {
        return ChoiceField::new('status', 'Statut')
            ->setChoices(QuizSessionStatus::getChoices())
            ->setTemplatePath('admin/quiz_session/quiz_session_status.html.twig')
            ->renderExpanded(false);
    }

    private function createScoreField(): IntegerField
    {
        return IntegerField::new('score', 'Score')
            ->setSortable(true);
    }

    private function createScoreFieldWithBadge(): IntegerField
    {
        return IntegerField::new('score', 'Score')
            ->setTemplatePath('admin/quiz_session/score_badge.html.twig')
            ->formatValue(fn ($value, QuizSession $entity) => $entity);
    }

    private function createCategoryField(): AssociationField
    {
        return AssociationField::new('category', 'Catégorie')
            ->setTemplatePath('admin/quiz_session/category_breadcrumb.html.twig');
    }

    private function createSubCategoryField(): AssociationField
    {
        return AssociationField::new('subCategory', 'Sous-catégorie')
            ->setTemplatePath('admin/quiz_session/category_breadcrumb.html.twig');
    }

    private function createDifficultiesField(): AssociationField
    {
        return AssociationField::new('difficulties', 'Difficultés')
            ->setCrudController(DifficultyCrudController::class)
            ->setTemplatePath('admin/quiz_session/difficulties_badges.html.twig');
    }

    private function createStartedAtField(): DateTimeField
    {
        return DateTimeField::new('startedAt', 'Commencé le')
            ->setFormat('dd/MM/yyyy HH:mm');
    }

    private function createFinishedAtField(): DateTimeField
    {
        return DateTimeField::new('finishedAt', 'Terminé le')
            ->setFormat('dd/MM/yyyy HH:mm');
    }

    private function createDurationField(): Field
    {
        return Field::new('duration', 'Durée')
            ->setTemplatePath('admin/quiz_session/session_duration.html.twig')
            ->setSortable(false);
    }

    private function createAnswersStatsField(): Field
    {
        return Field::new('answersStats', 'Réponses')
            ->setTemplatePath('admin/quiz_session/session_answers_stats.html.twig')
            ->setSortable(false);
    }

    private function createDetailedAnswersStatsField(): Field
    {
        return Field::new('detailedAnswersStats', 'Statistiques Détaillées')
            ->setTemplatePath('admin/quiz_session/session_detailed_stats.html.twig')
            ->setSortable(false)
            ->onlyOnDetail();
    }

    private function createAnswersListField(): AssociationField
    {
        return AssociationField::new('quizSessionAnswers', 'Liste des Réponses')
            ->setTemplatePath('admin/quiz_session/session_answers_list.html.twig')
            ->onlyOnDetail();
    }

    private function createAnswersCountField(): IntegerField
    {
        return IntegerField::new('quizSessionAnswers.count', 'Nb. Questions');
    }
}
