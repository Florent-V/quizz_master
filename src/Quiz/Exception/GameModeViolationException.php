<?php

declare(strict_types=1);

namespace App\Quiz\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(403)]
class GameModeViolationException extends \RuntimeException
{
    public function __construct(string $message, int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
