<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use App\Entity\Category;
use App\Quiz\Exception\QuizConflictException;
use App\Validator as CustomAssert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

trait CategoryTreeTrait
{
    #[Gedmo\TreeLeft]
    #[ORM\Column(name: 'lft', type: Types::INTEGER)]
    private ?int $lft = null;

    #[Gedmo\TreeRight]
    #[ORM\Column(name: 'rgt', type: Types::INTEGER)]
    private ?int $rgt = null;

    #[Gedmo\TreeLevel]
    #[ORM\Column(name: 'lvl', type: Types::INTEGER)]
    private ?int $lvl = null;

    #[Gedmo\TreeRoot]
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'tree_root', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Category $root = null;

    #[Gedmo\TreeParent]
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    #[CustomAssert\CategoryHierarchy]
    private ?Category $parent = null;

    /**
     * @var ArrayCollection <int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', fetch: 'EAGER')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $children;

    protected function initTree(): void
    {
        $this->children = new ArrayCollection();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function checkHierarchyLevel(): void
    {
        // $this->getParent() vient normalement de CategoryTreeTrait
        $parent = $this->getParent();

        if ($parent && $parent->getParent()) {
            throw new QuizConflictException(
                'Une catégorie ne peut pas avoir plus de deux niveaux (parent et enfant).'
            );
        }
    }

    public function getLft(): ?int
    {
        return $this->lft;
    }

    public function setLft(?int $lft): static
    {
        $this->lft = $lft;

        return $this;
    }

    public function getRgt(): ?int
    {
        return $this->rgt;
    }

    public function setRgt(?int $rgt): static
    {
        $this->rgt = $rgt;

        return $this;
    }

    public function getLvl(): ?int
    {
        return $this->lvl;
    }

    public function setLvl(?int $lvl): static
    {
        $this->lvl = $lvl;

        return $this;
    }

    public function getRoot(): ?self
    {
        return $this->root;
    }

    public function setRoot(?self $root = null): static
    {
        $this->root = $root;

        return $this;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildrenNoSoftDeleted(): Collection
    {
        return $this->children->filter(fn (self $child) => null === $child->getDeletedAt());
    }

    /**
     * @return Collection<int, self>
     */
    public function getActiveChildren(): Collection
    {
        return $this->children->filter(fn (self $child) => null === $child->getDeletedAt() && $child->isActive());
    }

    /**
     * Vérifie si cette catégorie est une catégorie parent (niveau 0).
     */
    public function isParentCategory(): bool
    {
        return null === $this->parent && 0 === $this->lvl;
    }

    /**
     * Vérifie si cette catégorie est une catégorie enfant (niveau 1).
     */
    public function isChildCategory(): bool
    {
        return null !== $this->parent && 1 === $this->lvl;
    }

    /**
     * Retourne le nombre d'enfants actifs.
     */
    public function getActiveChildrenCount(): int
    {
        return $this->getActiveChildren()->count();
    }

    /**
     * Retourne le chemin complet de la catégorie (Parent > Enfant).
     */
    public function getFullPath(): string
    {
        if ($this->isParentCategory()) {
            return $this->name ?? '';
        }

        return ($this->parent?->getName() ?? '') . ' > ' . ($this->name ?? '');
    }

    /**
     * Retourne le nom avec indentation selon le niveau.
     */
    public function getIndentedName(): string
    {
        $indent = str_repeat('— ', $this->lvl ?? 0);

        return $indent . ($this->name ?? '');
    }

    /**
     * Vérifie si la catégorie peut avoir des enfants.
     */
    public function canHaveChildren(): bool
    {
        return ($this->lvl ?? 0) < self::MAX_HIERARCHY_LEVEL;
    }
}
