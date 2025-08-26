<?php

declare(strict_types=1);

namespace App\Quiz\Exception;

/**
 * Exception levée lors des erreurs de validation de configuration de quiz.
 */
final class QuizValidationException extends \DomainException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
