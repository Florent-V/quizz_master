<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlameableEntity;
use App\Repository\QuizSessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuizSessionRepository::class)]
#[Gedmo\Loggable]
#[SoftDeleteable]
class QuizSession
{
    use TimestampableEntity;
    use BlameableEntity;
    use SoftDeleteableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    // @phpstan-ignore-next-line
    private ?int $id = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    #[Assert\NotNull]
    #[Assert\Type('integer')]
    private ?int $score = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Gedmo\Versioned]
    #[Assert\NotNull]
    #[Assert\Type('datetime')]
    private ?\DateTime $startedAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Gedmo\Versioned]
    #[Assert\NotNull]
    #[Assert\Type('datetime')]
    private ?\DateTime $finishedAt = null;

    #[ORM\ManyToOne(inversedBy: 'quizSessions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * @var Collection<int, QuizSessionAnswer>
     */
    #[ORM\OneToMany(targetEntity: QuizSessionAnswer::class, mappedBy: 'quizSession', orphanRemoval: true)]
    private Collection $quizSessionAnswers;

    public function __construct()
    {
        $this->quizSessionAnswers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getStartedAt(): ?\DateTime
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTime $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getFinishedAt(): ?\DateTime
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTime $finishedAt): static
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

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
            $quizSessionAnswer->setQuizSession($this);
        }

        return $this;
    }

    public function removeQuizSessionAnswer(QuizSessionAnswer $quizSessionAnswer): static
    {
        if ($this->quizSessionAnswers->removeElement($quizSessionAnswer)) {
            // set the owning side to null (unless already changed)
            if ($quizSessionAnswer->getQuizSession() === $this) {
                $quizSessionAnswer->setQuizSession(null);
            }
        }

        return $this;
    }
}
