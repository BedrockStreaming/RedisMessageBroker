<?php

declare(strict_types=1);

namespace M6Web\Component\RedisMessageBroker\MessageHandler;

use M6Web\Component\RedisMessageBroker\Event\ConsumerEvent;
use M6Web\Component\RedisMessageBroker\MessageEnvelope;
use M6Web\Component\RedisMessageBroker\Queue;
use Predis\Client as PredisClient;

/**
 * Class LostMessagesConsumer
 *
 * Get old messages in working lists and add them to queue list if the retry is'nt reached
 */
class LostMessagesConsumer extends AbstractMessageHandler
{
    /**
     * number of seconds when the message is considered old in a working list
     *
     * @var int
     */
    protected $messageTtl;

    /**
     * @var int
     */
    protected $maxRetry;

    /** @noinspection MagicMethodsValidityInspection */
    public function __construct(Queue\Definition $queue, PredisClient $redisClient, int $messageTtl, int $maxRetry = 1)
    {
        $this->messageTtl = $messageTtl;
        $this->maxRetry = $maxRetry;
        parent::__construct($queue, $redisClient);
    }

    public function setMessageTtl(int $messageTtl): void
    {
        $this->messageTtl = $messageTtl;
    }

    /**
     * Requeue message with date superior to message ttl
     * Then max retry reached, message is put in dead letter list
     */
    public function requeueOldMessages()
    {
        array_map(
            [$this, 'requeueOldMessageFromList'],
            iterator_to_array($this->queue->getWorkingLists($this->redisClient))
        );
    }

    protected function requeueOldMessageFromList(string $list)
    {
        for ($i = 0, $n = $this->redisClient->llen($list); $i < $n; $i++) {
            $serializedMessage = $this->redisClient->rpoplpush($list, $list);

            // check if message is old enough
            $message = MessageEnvelope::unserializeMessage($serializedMessage);
            if ((time() - $message->getUpdatedAt()->format('U')) > $this->messageTtl
                && $this->redisClient->lrem($list, 0, $serializedMessage)) {
                if ($message->getRetry() < $this->maxRetry) {
                    $this->addMessageInQueueList($message);

                    continue;
                }

                $this->addMessageInDeadLetterList($message);
            }
        }
    }

    protected function addMessageInDeadLetterList(MessageEnvelope $message)
    {
        $list = $this->queue->getDeadLetterListName();
        $this->redisClient->lpush($list, $message->getSerializedValue());

        if ($this->eventCallback) {
            ($this->eventCallback)(new ConsumerEvent(ConsumerEvent::MAX_RETRY_REACHED, 1, $list));
        }
    }

    protected function addMessageInQueueList(MessageEnvelope $message)
    {
        $message->incrementRetry();

        $queueName = $this->queue->getARandomListName();
        $this->redisClient->lpush($queueName, $message->getSerializedValue());

        if ($this->eventCallback) {
            ($this->eventCallback)(new ConsumerEvent(ConsumerEvent::REQUEUE_OLD_MESSAGE, 1, $queueName));
        }
    }
}
