<?php

namespace M6Web\Component\RedisMessageBroker\Queue;

use M6Web\Component\RedisMessageBroker\Message;

/**
 * Class Cleanup
 * clean the working list for a queue
 */
class Cleanup extends AbstractQueueTool
{
    /**
     * this method clean old message in the working list
     *
     * @param int $maxAge maximum age of a message
     */
    public function cleanOldMessages(int $maxAge)
    {
        // TODO
    }

    private function cleanMessage(callable $hasTodelete)
    {
        // TODO
        foreach ($this->getWorkingLists() as $redisList) {
            $listLen = $this->redisClient->llen($redisList);
            for ($i = 1; $i < $listLen; ++$i) {
                /** @var Message $message */
                $message = $this->redisClient->rpoplpush($redisList, $redisList);
            }
        }
    }

    /**
     * delete the redis lists
     */
    public function cleanReadyMessages()
    {
        // TODO
    }
}
