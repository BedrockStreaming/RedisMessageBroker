<?php

namespace M6Web\Component\RedisMessageBroker\Queue;

use Predis\Client as PredisClient;

abstract class AbstractQueueTool
{
    /**
     * @var Definition
     */
    protected $queue;

    /**
     * @var PredisClient
     */
    protected $redisClient;

    public function __construct(Definition $queue, PredisClient $redisClient)
    {
        $this->queue = $queue;
        $this->redisClient = $redisClient;
    }

    /**
     * return all working lists for a queue
     */
    public function getWorkingLists(): array
    {
        if ($r = $this->redisClient->keys($this->queue->getWorkingListPrefixName().'*')) {
            return $r;
        }

        return [];
    }
}
