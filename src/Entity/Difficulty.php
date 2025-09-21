<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlameableEntity;
use App\Quiz\Exception\QuizConflictException;
use App\Repository\DifficultyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DifficultyRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
class Difficulty
{
    use TimestampableEntity;
    use BlameableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['quiz:question:read'])]
    // @phpstan-ignore-next-line
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    #[Groups(['quiz:question:read'])]
    private ?string $name = null;

    #[ORM\Column(type: 'integer', unique: true)]
    #[Gedmo\Versioned]
    #[Assert\NotBlank]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $level = null;

    /**
     * @var Collection<int, Question>
     */
    #[ORM\OneToMany(
        targetEntity: Question::class,
        mappedBy: 'difficulty',
        cascade: ['persist']
    )]
    private Collection $questions;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $color = null;

    /**
     * @var Collection<int, QuizSession>
     */
    #[ORM\ManyToMany(targetEntity: QuizSession::class, mappedBy: 'difficulties')]
    private Collection $quizSessions;

    public function __construct()
    {
        $this->setCreatedAt(new \DateTime());
        $this->setUpdatedAt(new \DateTime());
        $this->questions    = new ArrayCollection();
        $this->quizSessions = new ArrayCollection();
    }

    #[ORM\PreRemove]
    public function checkQuestionsBeforeRemove(): void
    {
        if ($this->questions->count() > 0) {
            throw new QuizConflictException(
                'Impossible de supprimer une difficulté qui contient des questions.'
            );
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

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(int $level): static
    {
        $this->level = $level;

        return $this;
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
            $question->setDifficulty($this);
        }

        return $this;
    }

    public function removeQuestion(Question $question): static
    {
        if ($this->questions->removeElement($question)) {
            // set the owning side to null (unless already changed)
            if ($question->getDifficulty() === $this) {
                $question->setDifficulty(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getQuestionCount(): int
    {
        return $this->questions->count();
    }

    /**
     * @return Collection<int, QuizSession>
     */
    public function getQuizSessions(): Collection
    {
        return $this->quizSessions;
    }

    public function addQuizSession(QuizSession $quizSession): static
    {
        if (!$this->quizSessions->contains($quizSession)) {
            $this->quizSessions->add($quizSession);
            $quizSession->addDifficulty($this);
        }

        return $this;
    }

    public function removeQuizSession(QuizSession $quizSession): static
    {
        if ($this->quizSessions->removeElement($quizSession)) {
            $quizSession->removeDifficulty($this);
        }

        return $this;
    }
}
