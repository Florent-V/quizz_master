<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlameableEntity;
use App\Repository\ProposalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Gedmo\Translatable\Translatable;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: ProposalRepository::class)]
#[Gedmo\Loggable]
#[SoftDeleteable]
#[Vich\Uploadable]
class Proposal implements Translatable
{
    use TimestampableEntity;
    use BlameableEntity;
    use SoftDeleteableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    // @phpstan-ignore-next-line
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Gedmo\Translatable]
    #[Gedmo\Versioned]
    private ?string $content = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\Type('bool')]
    #[Gedmo\Versioned]
    private ?bool $isCorrect = null;

    #[Vich\UploadableField(mapping: 'proposal_image', fileNameProperty: 'imageName')]
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
    )]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $imageName = null;

    #[ORM\ManyToOne(inversedBy: 'proposals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Question $question = null;

    /**
     * @var Collection<int, QuizSessionAnswer>
     */
    #[ORM\OneToMany(targetEntity: QuizSessionAnswer::class, mappedBy: 'proposal')]
    private Collection $quizSessionAnswers;

    #[Gedmo\Locale]
    // @phpstan-ignore-next-line
    private ?string $locale = null;

    public function __construct()
    {
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

    public function isCorrect(): ?bool
    {
        return $this->isCorrect;
    }

    public function setIsCorrect(bool $isCorrect): static
    {
        $this->isCorrect = $isCorrect;

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

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function setQuestion(?Question $question): static
    {
        $this->question = $question;

        return $this;
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
            $quizSessionAnswer->setProposal($this);
        }

        return $this;
    }

    public function removeQuizSessionAnswer(QuizSessionAnswer $quizSessionAnswer): static
    {
        if ($this->quizSessionAnswers->removeElement($quizSessionAnswer)) {
            // set the owning side to null (unless already changed)
            if ($quizSessionAnswer->getProposal() === $this) {
                $quizSessionAnswer->setProposal(null);
            }
        }

        return $this;
    }

    public function getQuestionCategory(): ?Category
    {
        return $this->question?->getCategory();
    }

    public function getQuestionDifficulty(): Difficulty
    {
        return $this->question?->getDifficulty();
    }

    public function getAnswersCount(): int
    {
        return $this->quizSessionAnswers->count();
    }

    public function getSelectionPercentage(): string
    {
        $questionTotal = $this->question?->getQuizSessionAnswers()->count() ?? 0;
        if (0 === $questionTotal) {
            return 'Aucune réponse';
        }

        $correctCount = $this->quizSessionAnswers
            ->filter(fn (QuizSessionAnswer $answer) => $answer->getProposal()?->isCorrect())->count();
        $percentage = ($correctCount / $questionTotal) * 100;

        return sprintf('%.2f%%', $percentage);
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
}
