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
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

class CategoryFieldsConfigurationService
{
    use FieldsConfigurationTrait;

    public function __construct(protected TranslatorInterface $translator)
    {
        $this->setTranslator($this->translator);
    }

    /**
     * Retourne les champs configurés pour une page donnée.
     *
     * @return FieldInterface[]
     */
    public function getFieldsForPage(string $pageName, ?AdminContext $context = null): iterable
    {
        return match ($pageName) {
            Crud::PAGE_INDEX  => $this->buildIndexFields(),
            Crud::PAGE_DETAIL => $this->buildDetailFields(),
            Crud::PAGE_NEW, Crud::PAGE_EDIT => $this->buildFormFields($context),
            default => [],
        };
    }

    // === CHAMPS PAR PAGE ===
    /**
     * @return FieldInterface[]
     */
    private function buildIndexFields(): array
    {
        return [
            $this->createIdField(),
            $this->createNameFieldWithIcon(),
            $this->createParentField(),
            $this->createSlugField(),
            $this->createImageField(),
            $this->createQuestionsCountField(),
            $this->createStatusField(),
            $this->createStatsField(),
            $this->createTimestampField(),
        ];
    }

    /**
     * @return FieldInterface[]
     */
    private function buildDetailFields(): array
    {
        return [
            FormField::addPanel('📋 Informations Générales')->collapsible(),
            $this->createIdField(),
            $this->createNameField()->setTemplatePath('admin/fields/name_with_breadcrumb.html.twig'),
            $this->createSlugField(),
            $this->createDescriptionField(),
            $this->createIconField(),
            $this->createImageField(),

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
    private function buildFormFields(?AdminContext $context = null): array
    {
        return [
            FormField::addPanel('📝 Informations principales')->setIcon('fas fa-info-circle'),
            $this->createNameField()->setColumns(5),

            $this->createSlugField()
                ->setColumns(6)
                ->setFormTypeOption('disabled', true)
                ->setHelp('Généré automatiquement'),

            $this->createDescriptionFormField(),

            FormField::addPanel('🌳 Hiérarchie'),
            $this->createParentFormField($context),

            FormField::addPanel('🎨 Apparence'),
            $this->createIconField(),
            $this->createImageUploadField($context),
        ];
    }

    // === CREATION DES CHAMPS INDIVIDUELS ===
    private function createIdField(): IdField
    {
        return IdField::new('id')
            ->onlyOnIndex()
            ->hideOnForm();
    }

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

    private function createParentField(): AssociationField
    {
        //                return AssociationField::new('parent', 'Parent')
        //                    ->formatValue(fn ($value, Category $cat) => $cat->getParent()?->getName() ?? '—');
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

    private function createImageField(): ImageField
    {
        return ImageField::new('imageName', 'Image')
            ->setBasePath('/uploads/images/categories')
            ->setTemplatePath('admin/fields/generic_image.html.twig')
            ->hideOnIndex();
    }

    private function createImageUploadField(?AdminContext $context = null): TextField
    {
        return TextField::new('imageFile', 'Category Image')
            ->setFormType(VichImageType::class)
            ->setFormTypeOptions([
                'allow_delete'       => false,
                'delete_label'       => 'Supprimer',
                'download_label'     => 'Télécharger',
                'download_uri'       => false,
                'image_uri'          => true,
                'translation_domain' => 'VichUploaderBundle',
            ])
            ->setHelp('Upload an image (JPEG, PNG, WEBP, SVG). Max 5MB.')
            // ->setRequired(false)
            ->setRequired(Crud::PAGE_NEW === $context?->getCrud()->getCurrentPage())
            ->setColumns(12);
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
        //                return AssociationField::new('questions', 'Nb. Questions')
        //                    ->formatValue(function ($value, Category $category) {
        //                        return $category->getTotalQuestionsCount();
        //                    });
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
}
