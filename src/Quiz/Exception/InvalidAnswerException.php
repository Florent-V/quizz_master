<?php

declare(strict_types=1);

namespace App\Quiz\Exception;

class InvalidAnswerException extends \InvalidArgumentException
{
    public function __construct(
        string $message = 'Réponse invalide à la question.',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
