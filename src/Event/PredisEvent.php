<?php

declare(strict_types=1);

namespace M6Web\Component\RedisMessageBroker\Event;

class PredisEvent
{
    const EVENT_NAME = 'predis.command';

    /** @var string */
    protected $command;

    /** @var int */
    protected $executionTime;

    public function __construct(string $command, int $executionTime)
    {
        $this->command = $command;
        $this->executionTime = $executionTime;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getExecutionTime(): int
    {
        return $this->executionTime;
    }

    public function getTiming(): int
    {
        return $this->executionTime * 1000;
    }
}
