<?php

declare(strict_types=1);

namespace App\Quiz\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
class QuizConflictException extends \RuntimeException
{
    public function __construct(
        string $message = 'Conflict',
        int $code = 400,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
