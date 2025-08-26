<?php

declare(strict_types=1);

namespace App\Enum;

enum GameMode: string
{
    case TwentyQuestions = '20Q';
    case TimeAttack      = 'TIME_ATTACK';
    case SpeedRun        = 'SPEED_RUN';
    case SuddenDeath     = 'SUDDEN_DEATH';

    public function getLabel(): string
    {
        return match ($this) {
            self::TwentyQuestions => '20 Questions',
            self::TimeAttack      => 'Contre-la-montre',
            self::SpeedRun        => 'Speedrun',
            self::SuddenDeath     => 'Mort Subite',
        };
    }

    public function getQuestionLimit(): int
    {
        return match ($this) {
            self::TwentyQuestions => 20,
            self::TimeAttack, self::SpeedRun, self::SuddenDeath => 1,
        };
    }

    public function isActive(): bool
    {
        return match ($this) {
            self::TwentyQuestions, self::SuddenDeath => true,
            default => false,
        };
    }

    public function allowMultipleDifficulties(): bool
    {
        return match ($this) {
            self::TwentyQuestions => true,
            default               => false,
        };
    }

    public function isDifficultyRequired(): bool
    {
        return match ($this) {
            self::SuddenDeath => true,
            default           => false,
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
