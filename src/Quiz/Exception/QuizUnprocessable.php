<?php

declare(strict_types=1);

namespace App\Quiz\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(422)]
class QuizUnprocessable extends \RuntimeException
{
    public function __construct(
        string $message = 'Impossible de procéder à la requête, mauvais paramètres envoyés.',
        int $code = 400,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
