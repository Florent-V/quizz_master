<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Question;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/** @phpstan-ignore-next-line */
#[AsEntityListener(event: Events::prePersist, method: 'validateQuestionCategory', entity: Question::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'validateQuestionCategory', entity: Question::class)]
class QuestionCategoryValidationListener
{
    public function validateQuestionCategory(Question $question): void
    {
        $category = $question->getCategory();

        if ($category && null === $category->getParent()) {
            throw new \LogicException('Impossible d\'ajouter une question à une catégorie principale.');
        }
    }
}
