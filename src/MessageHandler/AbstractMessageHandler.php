<?php

declare(strict_types=1);

namespace M6Web\Component\RedisMessageBroker\MessageHandler;

use M6Web\Component\RedisMessageBroker\LoggedPredisClient\LoggedPredisClient;
use M6Web\Component\RedisMessageBroker\Queue;
use Predis\Client as PredisClient;

abstract class AbstractMessageHandler
{
    /**
     * @var Queue\Definition
     */
    protected $queue;

    /**
     * @var LoggedPredisClient
     */
    protected $redisClient;

    /**
     * Should messages be compressed in redis?
     *
     * @var bool
     */
    protected $doMessageCompression;

    /**
     * @var \Closure
     */
    protected $eventCallback;

    public function __construct(Queue\Definition $queue, PredisClient $redisClient, bool $doMessageCompression = false)
    {
        $this->queue = $queue;
        $this->doMessageCompression = $doMessageCompression;
        $this->redisClient = new LoggedPredisClient($redisClient);
    }

    /**
     * @param \Closure $closure
     *
     * @return $this
     */
    public function setEventCallback(\Closure $closure): self
    {
        $this->eventCallback = $closure;
        $this->redisClient->setEventCallback($closure);

        return $this;
    }
}
