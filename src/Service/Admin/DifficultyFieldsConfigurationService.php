<?php

declare(strict_types=1);

namespace App\Service\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

class DifficultyFieldsConfigurationService
{
    use FieldsConfigurationTrait;

    public function __construct(protected TranslatorInterface $translator)
    {
        $this->setTranslator($this->translator);
    }

    /**
     * @return FieldInterface[]
     */
    public function getFieldsForPage(string $pageName, ?AdminContext $context = null): array
    {
        return [
            TextField::new('name', $this->trans('difficulty.field.name'))
                ->setHelp($this->translator->trans('difficulty.help.name')),

            IntegerField::new('level', $this->trans('difficulty.field.level'))
                ->setHelp($this->translator->trans('difficulty.help.level')),

            ColorField::new('color', $this->trans('difficulty.field.color'))
                ->setHelp($this->translator->trans('difficulty.help.color')),

            $this->createdAtField(),
            $this->updatedAtField(),
        ];
    }
}
