<?php

declare(strict_types=1);

namespace M6Web\Component\RedisMessageBroker\Event;

use Symfony\Component\EventDispatcher\Event;

class PredisEvent extends Event
{
    const EVENT_NAME = 'predis.command';

    /** @var string */
    protected $command;

    /** @var float */
    protected $executionTime;

    public function __construct(string $command, float $executionTime)
    {
        $this->command = $command;
        $this->executionTime = $executionTime;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    /**
     * Get execution time in milliseconds (used by statsd).
     *
     * @return float
     */
    public function getTiming(): float
    {
        return $this->executionTime * 1000;
    }
}
