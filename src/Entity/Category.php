<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlameableEntity;
use App\Repository\CategoryRepository;
use App\Validator as CustomAssert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Gedmo\Translatable\Translatable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
#[Gedmo\SoftDeleteable]
#[Vich\Uploadable]
#[Gedmo\Tree(type: 'nested')]
#[UniqueEntity(fields: ['name'], message: 'Une catégorie avec ce nom existe déjà.')]
#[UniqueEntity(fields: ['slug'], message: 'Une catégorie avec ce slug existe déjà.')]
class Category implements Translatable
{
    use TimestampableEntity;
    use BlameableEntity;
    use SoftDeleteableEntity;

    public const int MAX_HIERARCHY_LEVEL = 1; // 0 = parent, 1 = enfant

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    // @phpstan-ignore-next-line
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    #[Gedmo\Translatable]
    #[Assert\NotBlank(message: 'Le nom de la catégorie est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $name = null;

    #[Gedmo\Slug(fields: ['name'], updatable: false)]
    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    #[Gedmo\Translatable]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Versioned]
    #[Assert\Length(
        max: 255,
        maxMessage: 'L\'icône ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $icon = null;

    #[Vich\UploadableField(mapping: 'category_image', fileNameProperty: 'imageName')]
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        maxSizeMessage: 'L\'image ne peut pas dépasser {{ limit }}.',
        mimeTypesMessage: 'Seuls les formats JPEG, PNG et WebP sont autorisés.'
    )]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $imageName = null;

    /**
     * @var Collection<int, Question>
     */
    #[ORM\OneToMany(
        targetEntity: Question::class,
        mappedBy: 'category',
        cascade: ['persist'],
        orphanRemoval: true
    )]
    private Collection $questions;

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
    #[ORM\ManyToOne(targetEntity: self::class)]
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

    #[Gedmo\Locale]
    // @phpstan-ignore-next-line
    private ?string $locale = null;

    public function __construct()
    {
        $this->setCreatedAt(new \DateTime());
        $this->setUpdatedAt(new \DateTime());
        $this->questions = new ArrayCollection();
        $this->children  = new ArrayCollection();
    }

    #[ORM\PreRemove]
    public function checkQuestionsBeforeRemove(): void
    {
        if ($this->questions->count() > 0) {
            throw new \LogicException('Impossible de supprimer une catégorie qui contient des questions.');
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;

        if (null !== $imageFile) {
            $this->updatedAt = new \DateTime();
        }
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageName(?string $imageName): void
    {
        $this->imageName = $imageName;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    /**
     * @return Collection<int, Question>
     */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(Question $question): static
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setCategory($this);
        }

        return $this;
    }

    public function removeQuestion(Question $question): static
    {
        if ($this->questions->removeElement($question)) {
            // set the owning side to null (unless already changed)
            if ($question->getCategory() === $this) {
                $question->setCategory(null);
            }
        }

        return $this;
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
    public function getActiveChildren(): Collection
    {
        return $this->children->filter(fn (self $child) => null === $child->getDeletedAt());
    }

    public function setTranslatableLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    // === MÉTHODES UTILITAIRES POUR LA HIÉRARCHIE ===
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
     * Retourne le nombre total de questions dans cette catégorie et ses enfants.
     */
    public function getTotalQuestionsCount(): int
    {
        $count = $this->questions->count();

        foreach ($this->children as $child) {
            $count += $child->getQuestions()->count();
        }

        return $count;
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

    /**
     * Retourne une représentation textuelle enrichie.
     */
    public function __toString(): string
    {
        return $this->getName() ?? 'Catégorie sans nom';
    }
}
