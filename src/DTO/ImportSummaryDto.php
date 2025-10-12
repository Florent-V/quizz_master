<?php

declare(strict_types=1);

namespace App\DTO;

final class ImportSummaryDto
{
    /**
     * @param string[] $errorMessages
     */
    public function __construct(
        public int $categoriesCreated = 0,
        public int $categoriesUpdated = 0,
        public int $questionsCreated = 0,
        public int $proposalsCreated = 0,
        public int $difficultiesCreated = 0,
        public int $errors = 0,
        public array $errorMessages = [],
    ) {
    }

    public function merge(ImportSummaryDto $other): self
    {
        $this->categoriesCreated   += $other->categoriesCreated;
        $this->categoriesUpdated   += $other->categoriesUpdated;
        $this->questionsCreated    += $other->questionsCreated;
        $this->proposalsCreated    += $other->proposalsCreated;
        $this->difficultiesCreated += $other->difficultiesCreated;
        $this->errors              += $other->errors;
        $this->errorMessages = array_merge($this->errorMessages, $other->errorMessages);

        return $this;
    }
}
