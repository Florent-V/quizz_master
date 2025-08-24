<?php

declare(strict_types=1);

namespace App\Quiz\Exception;

class NoMoreQuestionsException extends \Exception
{
    public function __construct(
        string $message = 'Plus de questions disponibles pour ce quiz.',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
