<?php

declare(strict_types=1);

namespace M6Web\Component\RedisMessageBroker\MessageHandler;

use M6Web\Component\RedisMessageBroker\Message;

/**
 * Class Producer
 * allow you to add message to the queue configured
 */
class Producer extends AbstractMessageHandler
{
    /**
     * produce a message
     * select a random redis list and rpush the message in it
     */
    public function publishMessage(Message $message)
    {
        // push message in the list
        $this->redisClient->lpush($this->queue->getARandomListName(), $message->getSerializedValue());
    }
}
