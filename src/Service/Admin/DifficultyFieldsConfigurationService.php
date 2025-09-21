<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\Difficulty;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class DifficultyFieldsConfigurationService extends AbstractFieldsConfigurationService
{
    use FieldsConfigurationTrait;

    /**
     * @return FieldInterface[]
     */
    protected function buildIndexFields(): array
    {
        return $this->getCommonFields();
    }

    /**
     * @return FieldInterface[]
     */
    protected function buildDetailFields(): array
    {
        return $this->getCommonFields();
    }

    /**
     * @return FieldInterface[]
     */
    protected function buildFormFields(?AdminContext $context = null): array
    {
        return $this->getCommonFields();
    }

    /**
     * @return FieldInterface[]
     */
    public function getCommonFields(): array
    {
        return [
            TextField::new('name', $this->trans('difficulty.field.name'))
                ->setHelp($this->translator->trans('difficulty.help.name')),

            IntegerField::new('level', $this->trans('difficulty.field.level'))
                ->setHelp($this->translator->trans('difficulty.help.level')),

            IntegerField::new('basePoints', $this->trans('difficulty.field.base_points'))
                ->setHelp($this->translator->trans('difficulty.help.base_points')),

            ColorField::new('color', $this->trans('difficulty.field.color'))
                ->setHelp($this->translator->trans('difficulty.help.color')),
            IntegerField::new('questionCount', 'Nombre de questions')
                ->formatValue(function ($value, Difficulty $difficulty) {
                    return $difficulty->getQuestionCount();
                })
                ->onlyOnIndex(),

            $this->createdAtField(),
            $this->updatedAtField(),
        ];
    }
}
