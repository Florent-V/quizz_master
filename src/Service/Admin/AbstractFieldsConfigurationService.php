<?php

declare(strict_types=1);

namespace App\Service\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractFieldsConfigurationService
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

    /**
     * À implémenter dans chaque sous-classe.
     *
     * @return FieldInterface[]
     */
    abstract protected function buildIndexFields(): array;

    /**
     * À implémenter dans chaque sous-classe.
     *
     * @return FieldInterface[]
     */
    abstract protected function buildDetailFields(): array;

    /**
     * À implémenter dans chaque sous-classe.
     *
     * @return FieldInterface[]
     */
    abstract protected function buildFormFields(?AdminContext $context = null): array;
}
