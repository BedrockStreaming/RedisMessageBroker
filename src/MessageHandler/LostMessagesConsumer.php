<?php

declare(strict_types=1);

namespace M6Web\Component\RedisMessageBroker\MessageHandler;

use M6Web\Component\RedisMessageBroker\Event\ConsumerEvent;
use M6Web\Component\RedisMessageBroker\MessageEnvelope;
use M6Web\Component\RedisMessageBroker\Queue;
use Predis\Client as PredisClient;

/**
 * Class Consumer
 *
 * Consume messages
 * two modes are available : auto-ack or no-ack
 * auto-ack mode will directly fech and erase the message from the queue. Its faster but unsafe.
 * ack mode will fetch the the message in a list unique for the worker. You will have to ack the message to erase it of the process list. Consumption is slower in ack mode
 *
 * working list as to be unique
 */
class LostMessagesConsumer extends AbstractMessageHandler
{
    /**
     * number of seconds to check before a message was considered old in a working list
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

    public function checkWorkingLists()
    {
        array_map([$this, 'checkList'], iterator_to_array($this->queue->getWorkingLists($this->redisClient)));
    }

    protected function checkList(string $list)
    {
        for ($i = 0, $n = $this->redisClient->llen($list); $i < $n; ++$i) {
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
        $message->setUpdatedAt(new \DateTime());
        $message->incrementRetry();

        $list = $this->queue->getARandomListName();
        $this->redisClient->lpush($list, $message->getSerializedValue());

        if ($this->eventCallback) {
            ($this->eventCallback)(new ConsumerEvent(ConsumerEvent::REQUEUE_OLD_MESSAGE, 1, $list));
        }
    }
}
