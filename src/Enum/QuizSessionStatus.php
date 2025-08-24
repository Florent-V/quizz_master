<?php

declare(strict_types=1);

namespace App\Enum;

enum QuizSessionStatus: string
{
    case InProgress = 'IN_PROGRESS';
    case Finished   = 'FINISHED';
    case Cancelled  = 'CANCELLED';
    case Failed     = 'FAILED';

    public function getLabel(): string
    {
        return match ($this) {
            self::InProgress => 'En cours',
            self::Finished   => 'Terminé',
            self::Cancelled  => 'Annulé',
            self::Failed     => 'Echec',
        };
    }
}
