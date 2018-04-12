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
    public function __construct(Queue\Definition $queue, PredisClient $redisClient, int $messageTtl, int $maxRetry = 1, bool $compressMessages = false)
    {
        $this->messageTtl = $messageTtl;
        $this->maxRetry = $maxRetry;
        parent::__construct($queue, $redisClient, $compressMessages);
    }

    public function setMessageTtl(int $messageTtl): void
    {
        $this->messageTtl = $messageTtl;
    }

    /**
     * Requeue message with date superior to message ttl
     * Then max retry reached, message is put in dead letter list
     *
     * @param int|null $maxExecuteTime
     */
    public function requeueOldMessages(?int $maxExecuteTime = null): void
    {
        $workingLists = iterator_to_array($this->queue->getWorkingLists($this->redisClient));

        $startTime = time();

        shuffle($workingLists);

        $restTime = null;

        foreach ($workingLists as $workingList) {
            if (null !== $maxExecuteTime) {
                $restTime = $maxExecuteTime - (time() - $startTime);

                if ($restTime <= 0) {
                    break;
                }
            }

            $this->requeueOldMessageFromList($workingList, $restTime);
        }
    }

    /**
     * Requeue old messages from $list, and remove them if
     * $messageTTL or time is exceed.
     *
     * @param string   $list
     * @param int|null $maxExecutionTime
     */
    protected function requeueOldMessageFromList(string $list, ?int $maxExecutionTime): void
    {
        $startTime = time();

        $maxMessages = $this->redisClient->llen($list);

        while (($maxMessages-- > 0) && ($storredMessage = $this->redisClient->rpoplpush($list, $list))) {
            $message = empty($storredMessage) ? null : MessageEnvelope::unstoreMessage($storredMessage, $this->doMessageCompression);

            if (!empty($message)) {
                // Message is old enough
                if ((time() - $message->getUpdatedAt()->format('U')) > $this->messageTtl) {
                    // Message is on the list
                    if ($this->redisClient->lrem($list, 0, $storredMessage)) {
                        // Message is not retry too many times
                        if ($message->getRetry() < $this->maxRetry) {
                            // Add it on the Queue list (again)
                            $this->addMessageInQueueList($message);
                        } else {
                            // Message is retried too many times; add it to the deadLetterList
                            $this->addMessageInDeadLetterList($message);
                        }
                    }
                } // Else : Message isn't too old : Do nothing.
            } else {
                // Message is empty, Remove it.
                $this->redisClient->lrem($list, 0, $storredMessage);
            }

            if (null !== $maxExecutionTime && $maxExecutionTime - (time() - $startTime) <= 0) {
                break;
            }
        }

        $this->redisClient->rpoplpush($list, $list);
    }

    protected function addMessageInDeadLetterList(MessageEnvelope $message): void
    {
        $list = $this->queue->getDeadLetterListName();
        $this->redisClient->lpush($list, $message->getStorableValue($this->doMessageCompression));

        if ($this->eventCallback) {
            ($this->eventCallback)(new ConsumerEvent(ConsumerEvent::MAX_RETRY_REACHED, 1, $list));
        }
    }

    protected function addMessageInQueueList(MessageEnvelope $message): void
    {
        $message->incrementRetry();

        $queueName = $this->queue->getARandomListName();
        $this->redisClient->lpush($queueName, $message->getStorableValue($this->doMessageCompression));

        if ($this->eventCallback) {
            ($this->eventCallback)(new ConsumerEvent(ConsumerEvent::REQUEUE_OLD_MESSAGE, 1, $queueName));
        }
    }
}
