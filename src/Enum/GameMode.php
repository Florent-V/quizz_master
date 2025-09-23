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
            self::TwentyQuestions, self::SpeedRun => 20,
            self::TimeAttack, self::SuddenDeath => 1,
        };
    }

    public function getTimeLimit(): int
    {
        return match ($this) {
            self::TimeAttack => 60,
            self::TwentyQuestions, self::SpeedRun, self::SuddenDeath => 0,
        };
    }

    public function getBonusTimePerGoodAnswer(): int
    {
        //  @TODO for future version
        //    return match ($this) {
        //       self::TimeAttack => 5,
        //       self::TwentyQuestions, self::SpeedRun, self::SuddenDeath => 0,
        //    };
        return 0;
    }

    public function isActive(): bool
    {
        return match ($this) {
            self::TwentyQuestions, self::SuddenDeath, self::TimeAttack => true,
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

    public function getRule(): string
    {
        return match ($this) {
            self::TwentyQuestions => 'Répondez à 20 questions à votre rythme. Pas de chrono, juste vos connaissances.',
            self::TimeAttack      => 'Combien de bonnes réponses pouvez-vous donner en 3 minutes ? '
                . 'Chaque bonne réponse ajoute un peu de temps !',
            self::SpeedRun => 'Terminez une série de 20 questions le plus vite possible. '
                . 'La rapidité est la clé !',
            self::SuddenDeath => 'Une seule mauvaise réponse et c\'est la fin. '
                . 'Enchaînez les bonnes réponses pour atteindre le meilleur score !',
        };
    }

    public function isDifficultyRequired(): bool
    {
        return match ($this) {
            self::SuddenDeath, self::TimeAttack => true,
            default => false,
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

    /**
     * Return badge configuration for game mode.
     *
     * @return array{icon: string, class: string, textClass: string}
     */
    public function getBadgeConfig(): array
    {
        return match ($this) {
            self::TwentyQuestions => ['icon' => 'fas fa-list-ol', 'class' => 'primary', 'textClass' => 'text-white'],
            self::TimeAttack      => ['icon' => 'fas fa-clock',    'class' => 'warning', 'textClass' => 'text-dark'],
            self::SpeedRun        => ['icon' => 'fas fa-running',  'class' => 'success', 'textClass' => 'text-white'],
            self::SuddenDeath     => ['icon' => 'fas fa-skull',    'class' => 'danger',  'textClass' => 'text-white'],
        };
    }
}
