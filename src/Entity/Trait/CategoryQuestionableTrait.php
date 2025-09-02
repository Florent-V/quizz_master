<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use App\Entity\Question;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

trait CategoryQuestionableTrait
{
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

    protected function initQuestions(): void
    {
        $this->questions = new ArrayCollection();
    }

    #[ORM\PreRemove]
    public function checkQuestionsBeforeRemove(): void
    {
        if ($this->questions->count() > 0) {
            throw new \LogicException('Impossible de supprimer une catégorie qui contient des questions.');
        }
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
}
