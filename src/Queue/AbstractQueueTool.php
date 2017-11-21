<?php

declare(strict_types=1);

namespace M6Web\Component\RedisMessageBroker\Queue;

use M6Web\Component\RedisMessageBroker\LoggedPredisClient\LoggedPredisClient;
use Predis\Client as PredisClient;

abstract class AbstractQueueTool
{
    /**
     * @var Definition
     */
    protected $queue;

    /**
     * @var LoggedPredisClient
     */
    protected $redisClient;

    /**
     * @var \Closure
     */
    protected $eventCallback;

    /**
     * @param Definition   $queue
     * @param PredisClient $redisClient
     */
    public function __construct(Definition $queue, PredisClient $redisClient)
    {
        $this->queue = $queue;
        $this->redisClient = new LoggedPredisClient($redisClient);
    }

    /**
     * @param \Closure $closure
     *
     * @return $this
     */
    public function setEventCallback(\Closure $closure)
    {
        $this->eventCallback = $closure;
        $this->redisClient->setEventCallback($closure);

        return $this;
    }
}
