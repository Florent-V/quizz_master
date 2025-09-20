<?php

declare(strict_types=1);

namespace App\Quiz\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(404)]
class QuizNotFoundException extends \RuntimeException
{
    public function __construct(
        string $message = 'Opération interdite.',
        int $code = 400,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
