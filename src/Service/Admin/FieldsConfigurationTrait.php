<?php

declare(strict_types=1);

namespace App\Service\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

trait FieldsConfigurationTrait
{
    protected TranslatorInterface $translator;

    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    protected function trans(string $key): string
    {
        return $this->translator->trans($key);
    }

    protected function createIdField(): IdField
    {
        return IdField::new('id')
            ->onlyOnIndex()
            ->hideOnForm();
    }

    protected function createTextField(string $property, string $label): TextField
    {
        return TextField::new($property, $label);
    }

    protected function createTextAreaField(string $property, string $label): TextareaField
    {
        return TextareaField::new($property, $label);
    }

    protected function createAssociationField(string $property, string $label): AssociationField
    {
        return AssociationField::new($property, $label);
    }

    protected function createImageField(string $property, string $label, string $basePath): ImageField
    {
        return ImageField::new($property, $label)
            ->setBasePath($basePath)
            ->setTemplatePath('admin/fields/generic_image.html.twig');
    }

    protected function createImageUploadField(string $property, string $label): TextField
    {
        return TextField::new($property, $label)
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
            ->setRequired(false)
            ->setColumns(12);
    }

    protected function createCollectionField(string $property, string $label): CollectionField
    {
        return CollectionField::new($property, $label);
    }

    protected function createBooleanField(string $property, string $label): BooleanField
    {
        return BooleanField::new($property, $label);
    }

    protected function createDateTimeField(string $property, string $label): DateTimeField
    {
        return match ($property) {
            'createdAt' => $this->createdAtField(),
            'updatedAt' => $this->updatedAtField(),
            'deletedAt' => $this->deletedAtField(),
            default     => DateTimeField::new($property, ucfirst($label))
                ->setFormat('dd/MM/yyyy HH:mm'),
        };
    }

    protected function createdAtField(): DateTimeField
    {
        return DateTimeField::new('createdAt', $this->trans('common.field.created_at'))
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm();
    }

    protected function updatedAtField(): DateTimeField
    {
        return DateTimeField::new('updatedAt', $this->trans('common.field.updated_at'))
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm();
    }

    protected function deletedAtField(): DateTimeField
    {
        return DateTimeField::new('deletedAt', $this->trans('common.field.deleted_at'))
            ->setFormat('dd/MM/yyyy HH:mm')
            ->setCssClass('text-danger')
            ->hideOnForm();
    }

    protected function createdByField(): AssociationField
    {
        return AssociationField::new('createdBy', $this->trans('common.field.created_by'))
            ->hideOnForm();
    }

    protected function updatedByField(): AssociationField
    {
        return AssociationField::new('updatedBy', $this->trans('common.field.updated_by'))
            ->hideOnForm();
    }

    protected function createStatusField(): BooleanField
    {
        return BooleanField::new('isDeleted', $this->trans('common.field.status'))
            ->setTemplatePath('admin/fields/is_deleted.html.twig')
            ->hideOnForm();
    }
}
