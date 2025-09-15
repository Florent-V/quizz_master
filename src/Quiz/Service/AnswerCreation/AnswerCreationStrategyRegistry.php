<?php

declare(strict_types=1);

namespace App\Quiz\Service\AnswerCreation;

use App\Enum\GameMode;

class AnswerCreationStrategyRegistry
{
    /** @var iterable<AnswerCreationStrategyInterface> */
    private iterable $strategies;

    /**
     * @param iterable<AnswerCreationStrategyInterface> $strategies
     */
    public function __construct(iterable $strategies)
    {
        $this->strategies = $strategies;
    }

    public function getStrategy(GameMode $gameMode): AnswerCreationStrategyInterface
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
