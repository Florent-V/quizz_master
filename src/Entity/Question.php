<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Trait\BlameableEntity;
use App\Repository\QuestionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Gedmo\Translatable\Translatable;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ApiResource(
    normalizationContext: ['groups' => ['quiz:question:read']],
    denormalizationContext: ['groups' => ['quiz:question:write']],
)]
#[ORM\Entity(repositoryClass: QuestionRepository::class)]
#[Gedmo\Loggable]
#[SoftDeleteable]
#[Vich\Uploadable]
class Question implements Translatable
{
    use TimestampableEntity;
    use BlameableEntity;
    use SoftDeleteableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['quiz:question:read'])]
    // @phpstan-ignore-next-line
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Gedmo\Versioned]
    #[Gedmo\Translatable]
    #[Assert\NotBlank]
    #[Groups(['quiz:question:read'])]
    private ?string $content = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    #[Gedmo\Translatable]
    #[Groups(['quiz:question:read'])]
    private ?string $explanation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    #[Groups(['quiz:question:read'])]
    private ?string $hint = null;

    #[Vich\UploadableField(mapping: 'question_image', fileNameProperty: 'imageName')]
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        maxSizeMessage: 'L\'image ne peut pas dépasser {{ limit }}.',
        mimeTypesMessage: 'Seuls les formats JPEG, PNG et WebP sont autorisés.'
    )]
    private ?File $imageFile = null;

    #[ORM\Column(nullable: true)]
    #[Gedmo\Versioned]
    #[Groups(['quiz:question:read'])]
    private ?string $imageName = null;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['quiz:question:read'])]
    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['quiz:question:read'])]
    private ?Difficulty $difficulty = null;

    /**
     * @var Collection<int, Proposal>
     */
    #[ORM\OneToMany(
        targetEntity: Proposal::class,
        mappedBy: 'question',
        cascade: ['persist'],
        fetch: 'EAGER',
        orphanRemoval: true
    )]
    #[Groups(['quiz:question:read'])]
    private Collection $proposals;

    /**
     * @var Collection<int, QuizSessionAnswer>
     */
    #[ORM\OneToMany(targetEntity: QuizSessionAnswer::class, mappedBy: 'question', orphanRemoval: true)]
    private Collection $quizSessionAnswers;

    #[Gedmo\Locale]
    // @phpstan-ignore-next-line
    private ?string $locale = null;

    #[ORM\Column]
    private bool $isActive = true;

    public function __construct()
    {
        $this->proposals          = new ArrayCollection();
        $this->quizSessionAnswers = new ArrayCollection();
        $this->setCreatedAt(new \DateTime());
        $this->setUpdatedAt(new \DateTime());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    public function setExplanation(?string $explanation): static
    {
        $this->explanation = $explanation;

        return $this;
    }

    public function getHint(): ?string
    {
        return $this->hint;
    }

    public function setHint(?string $hint): static
    {
        $this->hint = $hint;

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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getDifficulty(): ?Difficulty
    {
        return $this->difficulty;
    }

    public function setDifficulty(?Difficulty $difficulty): static
    {
        $this->difficulty = $difficulty;

        return $this;
    }

    /**
     * @return Collection<int, Proposal>
     */
    public function getProposals(): Collection
    {
        return $this->proposals;
    }

    public function addProposal(Proposal $proposal): static
    {
        if (!$this->proposals->contains($proposal)) {
            $this->proposals->add($proposal);
            $proposal->setQuestion($this);
        }

        return $this;
    }

    public function removeProposal(Proposal $proposal): static
    {
        if ($this->proposals->removeElement($proposal)) {
            // set the owning side to null (unless already changed)
            if ($proposal->getQuestion() === $this) {
                $proposal->setQuestion(null);
            }
        }

        return $this;
    }

    /**
     * Retourne le nombre total de proposition dans cette question.
     */
    public function getProposalsCount(): int
    {
        return $this->proposals->count();
    }

    /**
     * Retourne le nombre de propositions correctes pour cette question.
     */
    public function getCorrectProposalsCount(): int
    {
        return $this->proposals->filter(fn ($p) => $p->isCorrect())->count();
    }

    public function getTotalAnswersCount(): int
    {
        return $this->quizSessionAnswers->count();
    }

    public function getCorrectAnswersPercentage(): string
    {
        $total = $this->getTotalAnswersCount();
        if (0 === $total) {
            return 'Aucune réponse';
        }

        $correct    = $this->quizSessionAnswers->filter(fn ($a) => $a->getProposal()?->isCorrect())->count();
        $percentage = ($correct / $total) * 100;

        return sprintf('%.2f%%', $percentage);
    }

    /**
     * @return Collection<int, QuizSessionAnswer>
     */
    public function getQuizSessionAnswers(): Collection
    {
        return $this->quizSessionAnswers;
    }

    public function addQuizSessionAnswer(QuizSessionAnswer $quizSessionAnswer): static
    {
        if (!$this->quizSessionAnswers->contains($quizSessionAnswer)) {
            $this->quizSessionAnswers->add($quizSessionAnswer);
            $quizSessionAnswer->setQuestion($this);
        }

        return $this;
    }

    public function removeQuizSessionAnswer(QuizSessionAnswer $quizSessionAnswer): static
    {
        if ($this->quizSessionAnswers->removeElement($quizSessionAnswer)) {
            // set the owning side to null (unless already changed)
            if ($quizSessionAnswer->getQuestion() === $this) {
                $quizSessionAnswer->setQuestion(null);
            }
        }

        return $this;
    }

    public function setTranslatableLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function __toString(): string
    {
        return $this->content;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
