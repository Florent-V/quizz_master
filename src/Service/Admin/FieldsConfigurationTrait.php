<?php

declare(strict_types=1);

namespace App\Service\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    private function createTimestampField(): Field
    {
        return Field::new('updatedAt', $this->trans('common.field.updated_at'))
            ->setTemplatePath('admin/fields/relative_time.html.twig')
            ->setSortable(true)
            ->onlyOnIndex();
    }

    private function createdAtField(): DateTimeField
    {
        return DateTimeField::new('createdAt', $this->trans('common.field.created_at'))
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm();
    }

    private function updatedAtField(): DateTimeField
    {
        return DateTimeField::new('updatedAt', $this->trans('common.field.updated_at'))
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm();
    }

    private function deletedAtField(): DateTimeField
    {
        return DateTimeField::new('deletedAt', $this->trans('common.field.deleted_at'))
            ->setFormat('dd/MM/yyyy HH:mm')
            ->setCssClass('text-danger')
            ->hideOnForm();
    }

    private function createdByField(): AssociationField
    {
        return AssociationField::new('createdBy', $this->trans('common.field.created_by'))
            ->hideOnForm();
    }

    private function updatedByField(): AssociationField
    {
        return AssociationField::new('updatedBy', $this->trans('common.field.updated_by'))
            ->hideOnForm();
    }

    private function createStatusField(): BooleanField
    {
        return BooleanField::new('isDeleted', $this->trans('common.field.status'))
            ->setTemplatePath('admin/fields/is_deleted.html.twig')
            ->hideOnForm();
    }
}
