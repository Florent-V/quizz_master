<?php

declare(strict_types=1);

namespace App\Service\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;

trait FieldsConfigurationTrait
{
    private function createTimestampField(): Field
    {
        return Field::new('updatedAt', 'Modifié')
            ->setTemplatePath('admin/fields/relative_time.html.twig')
            ->setSortable(true)
            ->onlyOnIndex();
    }

    private function createdAtField(): DateTimeField
    {
        return DateTimeField::new('createdAt', 'Créée le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm();
    }

    private function updatedAtField(): DateTimeField
    {
        return DateTimeField::new('updatedAt', 'Modifiée le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm();
    }

    private function deletedAtField(): DateTimeField
    {
        return DateTimeField::new('deletedAt', 'Supprimée le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->setCssClass('text-danger')
            ->hideOnForm();
    }

    private function createdByField(): AssociationField
    {
        return AssociationField::new('createdBy', 'Créée par')
            ->hideOnForm();
    }

    private function updatedByField(): AssociationField
    {
        return AssociationField::new('updatedBy', 'Modifiée par')
            ->hideOnForm();
    }
}
