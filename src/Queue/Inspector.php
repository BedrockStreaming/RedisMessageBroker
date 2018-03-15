<?php

declare(strict_types=1);

namespace M6Web\Component\RedisMessageBroker\Queue;

/**
 * Class Inspector
 * Allow you to query the redis for a queue to get various informations
 */
class Inspector extends AbstractQueueTool
{
    /** @const string Key for Memory Section with command REDIS INFO */
    const REDIS_INFO_MEMORY = 'Memory';

    public function countReadyMessages(): int
    {
        $i = 0;
        foreach ($this->queue->getListNames() as $listName) {
            $i += $this->redisClient->llen($listName);
        }

        return $i;
    }

    public function countListQueues(): int
    {
        return iterator_count($this->queue->getQueueLists($this->redisClient));
    }

    public function countWorkingListQueues(): int
    {
        return iterator_count($this->queue->getWorkingLists($this->redisClient));
    }

    public function countInProgressMessages(): int
    {
        $i = 0;
        foreach ($this->queue->getWorkingLists($this->redisClient) as $list) {
            $i += $this->redisClient->llen($list);
        }

        return $i;
    }

    public function countInErrorMessages(): int
    {
        $i = 0;
        foreach ($this->queue->getDeadLetterLists($this->redisClient) as $list) {
            $i += $this->redisClient->llen($list);
        }

        return $i;
    }

    /**
     * Get the REDIS memory usage in bytes
     *
     * @see https://redis.io/commands/INFO
     */
    public function getRedisMemoryUsage(): ?int
    {
        $redisInfoMemory = $this->redisClient->info(self::REDIS_INFO_MEMORY);

        if (!empty($redisInfoMemory) && isset($redisInfoMemory[self::REDIS_INFO_MEMORY]['used_memory'])) {
            return $redisInfoMemory[self::REDIS_INFO_MEMORY]['used_memory'];
        }

        return null;
    }

    /**
     * Get the REDIS total memory in bytes
     *
     * @see https://redis.io/commands/INFO
     */
    public function getRedisMemoryTotal(): ?int
    {
        $redisInfoMemory = $this->redisClient->info(self::REDIS_INFO_MEMORY);

        if (!empty($redisInfoMemory) && isset($redisInfoMemory[self::REDIS_INFO_MEMORY]['total_system_memory'])) {
            return $redisInfoMemory[self::REDIS_INFO_MEMORY]['total_system_memory'];
        }

        return null;
    }

    /**
     * Calculate the REDIS free memory in bytes
     *
     * @see https://redis.io/commands/INFO
     */
    public function getRedisMemoryFree(): ?int
    {
        $redisInfoMemory = $this->redisClient->info(self::REDIS_INFO_MEMORY);

        if (!empty($redisInfoMemory) && isset($redisInfoMemory[self::REDIS_INFO_MEMORY]['total_system_memory']) && $redisInfoMemory[self::REDIS_INFO_MEMORY]['used_memory']) {
            return $redisInfoMemory[self::REDIS_INFO_MEMORY]['total_system_memory'] - $redisInfoMemory[self::REDIS_INFO_MEMORY]['used_memory'];
        }

        return null;
    }
}
