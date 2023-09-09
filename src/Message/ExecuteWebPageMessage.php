<?php

namespace App\Message;

class ExecuteWebPageMessage
{
    public function __construct(
        private readonly int $webPageId,
        private readonly bool $overrideSchedule = false,
    ) {}

    public function getWebPageId(): int
    {
        return $this->webPageId;
    }

    public function overridesSchedule(): bool
    {
        return $this->overrideSchedule;
    }
}