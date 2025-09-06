<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use App\Entity\QuizSession;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

trait CategoryQuizSessionTrait
{
    /**
     * @var Collection<int, QuizSession>
     */
    #[ORM\OneToMany(targetEntity: QuizSession::class, mappedBy: 'category')]
    private Collection $quizSessions;

    protected function initQuizSessions(): void
    {
        $this->quizSessions = new ArrayCollection();
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
            $quizSession->setCategory($this);
        }

        return $this;
    }

    public function removeQuizSession(QuizSession $quizSession): static
    {
        if ($this->quizSessions->removeElement($quizSession)) {
            // set the owning side to null (unless already changed)
            if ($quizSession->getCategory() === $this) {
                $quizSession->setCategory(null);
            }
        }

        return $this;
    }
}
