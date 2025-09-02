<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlameableEntity;
use App\Entity\Trait\CategoryQuestionableTrait;
use App\Entity\Trait\CategoryQuizSessionTrait;
use App\Entity\Trait\CategoryTreeTrait;
use App\Entity\Trait\MediaTrait;
use App\Repository\CategoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Gedmo\Translatable\Translatable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Annotation\Groups;
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
    use CategoryTreeTrait;
    use MediaTrait;
    use CategoryQuestionableTrait;
    use CategoryQuizSessionTrait;

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
    #[Groups(['quiz:question:read'])]
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

    #[Gedmo\Locale]
    // @phpstan-ignore-next-line
    private ?string $locale = null;

    public function __construct()
    {
        $this->setCreatedAt(new \DateTime());
        $this->setUpdatedAt(new \DateTime());
        $this->initTree();
        $this->initQuestions();
        $this->initQuizSessions();
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

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setTranslatableLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Retourne une représentation textuelle enrichie.
     */
    public function __toString(): string
    {
        return $this->getName() ?? 'Catégorie sans nom';
    }
}
