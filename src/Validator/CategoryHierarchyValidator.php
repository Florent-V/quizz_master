<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Category;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class CategoryHierarchyValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        $validConstraint = $this->isValidConstraint($constraint);
        if (!$this->shouldValidate($value)) {
            return;
        }

        // Récupération de l'entité courante via le contexte
        $currentCategory = $this->context->getObject();
        if (!$currentCategory instanceof Category) {
            return;
        }

        $this->validateHierarchy($value, $currentCategory, $validConstraint);
    }

    private function hasCircularReference(Category $category, Category $parent): bool
    {
        $visited = [];
        $current = $parent;

        while (null !== $current) {
            if ($current->getId() === $category->getId()) {
                return true;
            }

            if (in_array($current->getId(), $visited)) {
                // Protection contre les boucles infinies
                break;
            }

            $visited[] = $current->getId();
            $current   = $current->getParent();
        }

        return false;
    }

    private function isValidConstraint(Constraint $constraint): CategoryHierarchy
    {
        if (!$constraint instanceof CategoryHierarchy) {
            throw new UnexpectedTypeException($constraint, CategoryHierarchy::class);
        }

        return $constraint;
    }

    private function shouldValidate(mixed $value): bool
    {
        if (null === $value || '' === $value) {
            return false; // handled by NotBlank
        }

        if (!$value instanceof Category) {
            throw new UnexpectedValueException($value, Category::class);
        }

        return true;
    }

    private function validateHierarchy(Category $parent, Category $current, CategoryHierarchy $constraint): void
    {
        $this->validateMaxLevel($parent, $constraint);
        $this->validateSelfReference($parent, $current, $constraint);
        $this->validateCircularReference($current, $parent, $constraint);
    }

    private function validateMaxLevel(Category $parent, CategoryHierarchy $constraint): void
    {
        if (($parent->getLvl() ?? 0) >= Category::MAX_HIERARCHY_LEVEL) {
            $this->context->buildViolation($constraint->maxLevelMessage)
                ->addViolation();
        }
    }

    private function validateSelfReference(Category $parent, Category $current, CategoryHierarchy $constraint): void
    {
        if ($parent->getId() === $current->getId()) {
            $this->context->buildViolation($constraint->selfParentMessage)
                ->addViolation();
        }
    }

    private function validateCircularReference(Category $current, Category $parent, CategoryHierarchy $constraint): void
    {
        if ($this->hasCircularReference($current, $parent)) {
            $this->context->buildViolation($constraint->circularReferenceMessage)
                ->addViolation();
        }
    }
}
