<?php

declare(strict_types=1);

namespace App\Quiz\Exception;

class InvalidQuizSessionException extends \InvalidArgumentException
{
    public function __construct(
        string $message = 'Session de quiz invalide ou inexistante. Veuillez recommencer.',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
