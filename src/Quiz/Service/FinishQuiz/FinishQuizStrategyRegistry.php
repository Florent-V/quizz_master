<?php

declare(strict_types=1);

namespace App\Quiz\Service\FinishQuiz;

use App\Enum\GameMode;

class FinishQuizStrategyRegistry
{
    /** @var iterable<FinishQuizStrategyInterface> */
    private iterable $strategies;

    /**
     * @param iterable<FinishQuizStrategyInterface> $strategies
     */
    public function __construct(iterable $strategies)
    {
        $this->strategies = $strategies;
    }

    public function getStrategy(GameMode $gameMode): FinishQuizStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($gameMode)) {
                return $strategy;
            }
        }

        throw new \InvalidArgumentException(
            sprintf('No strategy found for game mode: %s', $gameMode->value)
        );
    }
}
