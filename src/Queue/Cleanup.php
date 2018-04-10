<?php

declare(strict_types=1);

namespace M6Web\Component\RedisMessageBroker\Queue;

use M6Web\Component\RedisMessageBroker\MessageEnvelope;

/**
 * Class Cleanup
 * clean the working list or the ready message list for a queue
 * those methods are slow and should be not used
 */
class Cleanup extends AbstractQueueTool
{
    /**
     * this method clean old message in the working list
     *
     * @param int  $maxAge             maximum age of a message
     * @param bool $eraseReadyMessages try to erase ready messages too ?
     *
     * @return int number of erased messages
     */
    public function cleanWorkingListsOldMessages(int $maxAge, bool $eraseReadyMessages = false): int
    {
        $r = 0;
        $hasToDelete = function (MessageEnvelope $message) use ($maxAge) {
            return (time() - $message->getCreatedAt()->format('U')) > $maxAge; // if message was created before $maxAge
        };
        $r += $this->cleanMessage($this->queue->getWorkingLists($this->redisClient), $hasToDelete);

        if ($eraseReadyMessages) {
            $r += $this->cleanMessage($this->queue->getListNames(), $hasToDelete);
        }

        return $r;
    }

    /**
     * this method delete deadletter lists
     *
     * @return int number of deleted messages in dead letter lists
     */
    public function cleanDeadLetterLists(): int
    {
        $r = 0;

        foreach ($this->queue->getDeadLetterLists($this->redisClient) as $deadLetterList) {
            $this->redisClient->multi();
            $this->redisClient->llen($deadLetterList);
            $this->redisClient->del($deadLetterList);
            $result = $this->redisClient->exec();

            $r += $result[0] ?? 0;
        }

        return $r;
    }

    private function cleanMessage(iterable $lists, callable $hasToDelete): int
    {
        $r = 0;
        foreach ($lists as $list) {
            $listLen = $this->redisClient->llen($list);
            for ($i = 1; $i <= $listLen; $i++) {
                $message = $this->redisClient->rpoplpush($list, $list);
                if ($hasToDelete(MessageEnvelope::unstoreMessage($message))) {
                    $r += $this->redisClient->lrem($list, 0, $message);
                }
            }
        }

        return $r;
    }
}
