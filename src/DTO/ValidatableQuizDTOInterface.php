<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\GameMode;

/**
 * Interface marquant un DTO comme validable par le service de validation.
 */
interface ValidatableQuizDTOInterface
{
    public function getDifficultiesCount(): int;

    public function getGameMode(): GameMode;
}
