<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\DTO\AnswerInputDto;
use App\DTO\AnswerOutputDto;
use App\Entity\Trait\BlameableEntity;
use App\Enum\GameMode;
use App\Enum\QuizSessionStatus;
use App\Quiz\State\QuizAnswerProcessor;
use App\Repository\QuizSessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/quiz_sessions/{id}/answer',
            description: 'Submit an answer for a quiz session',
            input: AnswerInputDto::class,
            output: AnswerOutputDto::class,
            processor: QuizAnswerProcessor::class
        ),
    ],
    // We can add default GET operations if needed, but for now, we only need the custom POST
    // For example:
    // collectionOperations: ['get'],
    // itemOperations: ['get']
)]
#[ORM\Entity(repositoryClass: QuizSessionRepository::class)]
#[Gedmo\Loggable]
#[SoftDeleteable]
class QuizSession
{
    use TimestampableEntity;
    use BlameableEntity;
    use SoftDeleteableEntity;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    // @phpstan-ignore-next-line
    private ?Uuid $id = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    #[Assert\NotNull]
    #[Assert\Type('integer')]
    private ?int $score = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Versioned]
    #[Assert\NotNull]
    #[Assert\Type('datetime')]
    private ?\DateTime $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Gedmo\Versioned]
    #[Assert\NotNull]
    #[Assert\Type('datetime')]
    private ?\DateTime $finishedAt = null;

    #[ORM\ManyToOne(inversedBy: 'quizSessions')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    /**
     * @var Collection<int, QuizSessionAnswer>
     */
    #[ORM\OneToMany(
        targetEntity: QuizSessionAnswer::class,
        mappedBy: 'quizSession',
        cascade: ['persist'],
        orphanRemoval: true
    )]
    private Collection $quizSessionAnswers;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Versioned]
    private ?QuizSessionStatus $status = null;

    #[ORM\Column(length: 255)]
    private ?string $pseudo = null;

    #[ORM\Column(length: 255)]
    private ?GameMode $gameMode = null;

    #[ORM\ManyToOne(inversedBy: 'quizSessions')]
    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'quizSubSessions')]
    private ?Category $subCategory = null;

    /**
     * @var Collection<int, Difficulty>
     */
    #[ORM\ManyToMany(targetEntity: Difficulty::class, inversedBy: 'quizSessions')]
    private Collection $difficulties;

    public function __construct()
    {
        $this->setCreatedAt(new \DateTime());
        $this->setUpdatedAt(new \DateTime());
        $this->quizSessionAnswers = new ArrayCollection();
        $this->difficulties       = new ArrayCollection();
    }

    public function getId(): ?Uuid
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

    public function getStatus(): ?QuizSessionStatus
    {
        return $this->status;
    }

    public function setStatus(?QuizSessionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    public function getGameMode(): ?GameMode
    {
        return $this->gameMode;
    }

    public function setGameMode(GameMode $gameMode): static
    {
        $this->gameMode = $gameMode;

        return $this;
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

    public function getSubCategory(): ?Category
    {
        return $this->subCategory;
    }

    public function setSubCategory(?Category $subCategory): static
    {
        $this->subCategory = $subCategory;

        return $this;
    }

    /**
     * @return Collection<int, Difficulty>
     */
    public function getDifficulties(): Collection
    {
        return $this->difficulties;
    }

    public function addDifficulty(Difficulty $difficulty): static
    {
        if (!$this->difficulties->contains($difficulty)) {
            $this->difficulties->add($difficulty);
        }

        return $this;
    }

    public function removeDifficulty(Difficulty $difficulty): static
    {
        $this->difficulties->removeElement($difficulty);

        return $this;
    }
}
