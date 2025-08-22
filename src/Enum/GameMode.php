<?php

declare(strict_types=1);

namespace App\Enum;

enum GameMode: string
{
    case TwentyQuestions = '20Q';
    case TimeAttack      = 'TIME_ATTACK';
    case SpeedRun        = 'SPEED_RUN';

    public function getLabel(): string
    {
        return match ($this) {
            self::TwentyQuestions => '20 Questions',
            self::TimeAttack      => 'Contre-la-montre',
            self::SpeedRun        => 'Speedrun',
        };
    }

    public function getQuestionLimit(): ?int
    {
        return match ($this) {
            self::TwentyQuestions => 20,
            self::TimeAttack, self::SpeedRun => null,
        };
    }

    /**
     * @return array<string,string>
     */
    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case->value;
        }

        return $choices;
    }
}
