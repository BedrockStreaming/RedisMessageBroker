<?php

declare(strict_types=1);

namespace M6Web\Component\RedisMessageBroker\Queue;

/**
 * Class Inspector
 * Allow you to query the redis for a queue to get various informations
 */
class Inspector extends AbstractQueueTool
{
    public function countReadyMessages(): int
    {
        $i = 0;
        foreach ($this->queue->getListNames() as $listName) {
            $i += $this->redisClient->llen($listName);
        }

        return $i;
    }

    public function countInProgressMessages(): int
    {
        $i = 0;
        foreach ($this->redisClient->keys($this->queue->getWorkingListPrefixName().'*') as $list) {
            $i += $this->redisClient->llen($list);
        }

        return $i;
    }
}
