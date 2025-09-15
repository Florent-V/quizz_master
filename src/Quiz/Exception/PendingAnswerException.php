<?php

declare(strict_types=1);

namespace App\Quiz\Exception;

use App\Entity\QuizSessionAnswer;

class PendingAnswerException extends \RuntimeException
{
    private QuizSessionAnswer $pendingAnswer;

    public function __construct(
        string $message,
        QuizSessionAnswer $pendingAnswer,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->pendingAnswer = $pendingAnswer;
    }

    public function getPendingAnswer(): QuizSessionAnswer
    {
        return $this->pendingAnswer;
    }
}
