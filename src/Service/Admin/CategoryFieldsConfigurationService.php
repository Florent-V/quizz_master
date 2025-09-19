<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\Category;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Validator\Constraints as Assert;

class CategoryFieldsConfigurationService extends AbstractFieldsConfigurationService
{
    // === CHAMPS PAR PAGE ===
    /**
     * @return FieldInterface[]
     */
    protected function buildIndexFields(): array
    {
        return [
            $this->createIdField(),
            $this->createNameFieldWithIcon(),
            $this->createParentField(),
            $this->createSlugField(),
            $this->isImageField(),
            $this->createQuestionsCountField(),
            $this->createIsActiveField(),
            $this->createStatusField(),
            $this->createStatsField(),
            $this->createDateTimeField('createdAt', 'Créé le'),
        ];
    }

    /**
     * @return FieldInterface[]
     */
    protected function buildDetailFields(): array
    {
        return [
            FormField::addPanel('📋 Informations Générales')->collapsible(),
            $this->createIdField(),
            $this->createNameField()->setTemplatePath('admin/fields/name_with_breadcrumb.html.twig'),
            $this->createSlugField(),
            $this->createDescriptionField(),
            $this->createIconField(),
            $this->createImageField('imageName', 'Image', '/uploads/images/categories'),

            FormField::addPanel('Activation'),
            $this->createIsActiveField(),

            FormField::addPanel('🌳 Hiérarchie')->collapsible(),
            $this->createParentField(),
            IntegerField::new('lvl', 'Niveau')
                ->formatValue(fn ($value) => match ($value) {
                    0 => 'Parent', 1 => 'Enfant', default => "Niveau $value",
                }),
            $this->createChildrenField(),
            $this->createChildrenCountField(),

            FormField::addPanel('📊 Statistiques')->collapsible(),
            $this->createDirectQuestionsCountField(),
            $this->createQuestionsCountField(),

            FormField::addPanel('🏷️ Métadonnées')->collapsible(),
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
        return [
            FormField::addPanel('📝 Informations principales')->setIcon('fas fa-info-circle'),
            $this->createNameField()->setColumns(5),

            $this->createSlugField()
                ->setColumns(6)
                ->setFormTypeOption('disabled', true)
                ->setHelp('Généré automatiquement'),

            $this->createDescriptionFormField(),

            FormField::addPanel('Activation'),
            $this->createIsActiveField(),

            FormField::addPanel('🌳 Hiérarchie'),
            $this->createParentFormField($context),

            FormField::addPanel('🎨 Apparence'),
            $this->createIconField(),
            $this->createCategoryImageUploadField($context),
        ];
    }

    // === CREATION DES CHAMPS INDIVIDUELS ===
    private function createIconField(): TextField
    {
        return TextField::new('icon', 'Icon')
            ->setRequired(false)
            ->setMaxLength(100)
            ->setTemplatePath('admin/fields/generic_icon.html.twig')
            ->setHelp('Symfony UX icon name (e.g., "logos:react", "logos:laravel"). Leave empty for default.');
    }

    private function createNameField(): TextField
    {
        return TextField::new('name', 'Nom')
            ->setRequired(true)
            ->setMaxLength(100)
            ->setHelp('Nom unique de la catégorie')
            ->addCssClass('fw-bold')
            ->setFormTypeOptions([
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 2, max: 100),
                ],
            ]);
    }

    private function createNameFieldWithIcon(): TextField
    {
        return TextField::new('name', 'Nom')
            ->setTemplatePath('admin/fields/generic_name_with_icon.html.twig')
            ->formatValue(fn ($value, $entity) => $entity);
    }

    private function createSlugField(): SlugField
    {
        return SlugField::new('slug')
            ->setTargetFieldName('name')
            ->hideOnIndex();
    }

    private function createDescriptionField(): TextareaField
    {
        return TextareaField::new('description', 'Description')
            ->renderAsHtml();
    }

    private function createDescriptionFormField(): TextEditorField
    {
        return TextEditorField::new('description', 'Description')
            ->setRequired(false)
            ->setNumOfRows(5)
            ->setHelp('Description optionnelle');
    }

    private function isImageField(): TextField
    {
        return TextField::new('imageName', 'Image')
            ->formatValue(function ($value, Category $category) {
                return empty($category->getImageName()) ? '🚫' : '✅';
            })
            ->setSortable(false);
    }

    private function createParentField(): AssociationField
    {
        // TODO rendre le nom clickable
        return AssociationField::new('parent', 'Parent')
            ->setTemplatePath('admin/fields/category_parent_breadcrumb.html.twig')
            ->hideOnForm();
    }

    private function createParentFormField(?AdminContext $context = null): AssociationField
    {
        return AssociationField::new('parent', 'Catégorie parente')
            ->setRequired(false)
            ->setQueryBuilder(function (QueryBuilder $qb) use ($context) {
                $currentId = $context?->getEntity()->getInstance()?->getId();
                $qb->andWhere('entity.lvl = 0')
                    ->orderBy('entity.name', 'ASC');

                if ($currentId) {
                    $qb->andWhere('entity.id != :id')
                        ->setParameter('id', $currentId);
                }

                return $qb;
            })
            ->setHelp('Laissez vide pour une catégorie principale');
    }

    private function createChildrenCountField(): AssociationField
    {
        return AssociationField::new('children', 'Enfants')
            ->formatValue(fn ($value, Category $cat) => $cat->getActiveChildrenCount() . ' sous catégorie(s)');
    }

    private function createChildrenField(): AssociationField
    {
        return AssociationField::new('children', 'Sous-catégories')
            ->setTemplatePath('admin/fields/category_children_list.html.twig')
            ->onlyOnDetail();
    }

    protected function createCategoryImageUploadField(?AdminContext $context = null): TextField
    {
        return $this->createImageUploadField('imageFile', 'Category Image')
            ->setRequired(Crud::PAGE_NEW === $context?->getCrud()->getCurrentPage());
    }

    private function createDirectQuestionsCountField(): AssociationField
    {
        return AssociationField::new('questions', 'Nb. Questions Directes')
            ->formatValue(function ($value, Category $category) {
                return $category->getQuestions()->count();
            });
    }

    private function createQuestionsCountField(): Field
    {
        return Field::new('questionsCount', 'Total Questions')
            ->setTemplatePath('admin/fields/questions_count_badge.html.twig')
            ->setSortable(false);
    }

    private function createStatsField(): Field
    {
        return Field::new('stats', 'Statistiques')
            ->setTemplatePath('admin/fields/category_quick_stats.html.twig')
            ->onlyOnIndex()
            ->setSortable(false);
    }

    private function createIsActiveField(): Field
    {
        return Field::new('isActive', 'Active')
            ->setHelp('Si la catégorie est active, elle sera visible pour les joueurs.');
    }
}
