<?php

declare(strict_types=1);

namespace App\Quiz\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(400)]
class AnswerException extends \RuntimeException
{
    public function __construct(
        string $message = 'Réponse invalide à la question.',
        int $code = 400,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
