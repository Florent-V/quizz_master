<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlameableEntity;
use App\Repository\QuizSessionAnswerRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: QuizSessionAnswerRepository::class)]
#[Gedmo\Loggable]
#[SoftDeleteable]
class QuizSessionAnswer
{
    use TimestampableEntity;
    use BlameableEntity;
    use SoftDeleteableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    // @phpstan-ignore-next-line
    private ?int $id = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    #[Gedmo\Versioned]
    private ?bool $isCorrect = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Gedmo\Versioned]
    private ?int $time = null;

    #[ORM\ManyToOne(inversedBy: 'quizSessionAnswers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?QuizSession $quizSession = null;

    #[ORM\ManyToOne(inversedBy: 'quizSessionAnswers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Question $question = null;

    #[ORM\ManyToOne(inversedBy: 'quizSessionAnswers')]
    private ?Proposal $proposal = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $askedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $answeredAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTime(): ?int
    {
        return $this->time;
    }

    public function setTime(int $time): static
    {
        $this->time = $time;

        return $this;
    }

    public function getQuizSession(): ?QuizSession
    {
        return $this->quizSession;
    }

    public function setQuizSession(?QuizSession $quizSession): static
    {
        $this->quizSession = $quizSession;

        return $this;
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

    public function getProposal(): ?Proposal
    {
        return $this->proposal;
    }

    public function setProposal(?Proposal $proposal): static
    {
        $this->proposal = $proposal;

        return $this;
    }

    public function getAskedAt(): ?\DateTimeImmutable
    {
        return $this->askedAt;
    }

    public function setAskedAt(\DateTimeImmutable $askedAt): static
    {
        $this->askedAt = $askedAt;

        return $this;
    }

    public function getAnsweredAt(): ?\DateTimeImmutable
    {
        return $this->answeredAt;
    }

    public function setAnsweredAt(\DateTimeImmutable $answeredAt): static
    {
        $this->answeredAt = $answeredAt;

        return $this;
    }
}
