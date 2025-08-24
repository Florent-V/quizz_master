<?php

declare(strict_types=1);

namespace App\Quiz\Exception;

class InvalidSessionException extends \InvalidArgumentException
{
    public function __construct(
        string $message = 'Session invalide.',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
