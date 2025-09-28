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

    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case->value;
        }

        return $choices;
    }

    /**
     * Returns the badge configuration for the current quiz session status.
     *
     * @return array{
     *     icon: string,
     *     class: string,
     *     textClass: string
     * }
     */
    public function getBadgeConfig(): array
    {
        return match ($this) {
            self::InProgress => [
                'icon'      => 'fas fa-play',
                'class'     => 'warning',
                'textClass' => 'text-dark',
            ],
            self::Finished => [
                'icon'      => 'fas fa-check',
                'class'     => 'success',
                'textClass' => 'text-white',
            ],
            self::Cancelled => [
                'icon'      => 'fas fa-times',
                'class'     => 'error',
                'textClass' => 'text-white',
            ],
            self::Failed => [
                'icon'      => 'fas fa-exclamation-triangle',
                'class'     => 'danger',
                'textClass' => 'text-white',
            ],
        };
    }
}
