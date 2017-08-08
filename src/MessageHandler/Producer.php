<?php

declare(strict_types=1);

namespace M6Web\Component\RedisMessageBroker\MessageHandler;

use M6Web\Component\RedisMessageBroker\MessageEnvelope;

/**
 * Class Producer
 * allow you to add message to the queue configured
 */
class Producer extends AbstractMessageHandler
{
    /**
     * produce a message
     * select a random redis list and lpush the message in it
     *
     * @param MessageEnvelope $message the message
     *
     * @return int the number of message in the physical redis list. Be aware thats not necessary the number of the message in the queue
     */
    public function publishMessage(MessageEnvelope $message): int
    {
        // push message in the list
        return $this->redisClient->lpush($this->queue->getARandomListName(), $message->getSerializedValue());
    }
}
