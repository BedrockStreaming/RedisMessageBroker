<?php

namespace M6Web\Component\RedisMessageBroker\MessageHandler;

use M6Web\Component\RedisMessageBroker\Queue;
use Predis\Client as PredisClient;

abstract class AbstractMessageHandler
{
    /**
     * @var Queue\Definition
     */
    protected $queue;

    /**
     * @var PredisClient
     */
    protected $redisClient;

    public function __construct(Queue\Definition $queue, PredisClient $redisClient)
    {
        $this->queue = $queue;
        $this->redisClient = $redisClient;
    }
}
