<?php

declare(strict_types=1);

namespace App\Quiz\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(400)]
class QuizSessionException extends \RuntimeException
{
    public function __construct(
        string $message = 'Session de quiz invalide ou inexistante. Veuillez recommencer.',
        int $code = 400,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
