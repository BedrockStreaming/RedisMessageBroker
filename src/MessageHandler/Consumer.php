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
class Consumer extends AbstractMessageHandler
{
    private $autoAck = true;

    /**
     * uniqueId can be used to setup a unique workling list
     *
     * @var string
     */
    private $uniqueId;

    /** @noinspection MagicMethodsValidityInspection */
    public function __construct(Queue\Definition $queue, PredisClient $redisClient, string $uniqueId)
    {
        $this->uniqueId = $uniqueId;
        parent::__construct($queue, $redisClient);
    }

    public function setNoAutoAck()
    {
        $this->autoAck = false;
    }

    public function getMessage(): ?MessageEnvelope
    {
        $lists = iterator_to_array($this->queue->getQueueLists($this->redisClient));
        shuffle($lists);

        // #1 autoack
        if ($this->autoAck) {
            foreach ($lists as $list) {
                if ($message = $this->redisClient->rpop($list)) {
                    return MessageEnvelope::unserializeMessage($message);
                }
            }
        }

        // #2 no-autoack - messages have to be acked manually via ->ack(message)
        // grab something in the queue and put it in the workinglist while returning the message
        foreach ($lists as $list) {
            if ($message = $this->redisClient->rpoplpush($list, $this->getWorkingList())) {
                return MessageEnvelope::unserializeMessage($message);
            }
        }

        return null;
    }

    /**
     * @param MessageEnvelope $message this message will be acked in the working lists
     *
     * @return int the number of messages acked
     */
    public function ack(MessageEnvelope $message): int
    {
        $nbMessageAck = $this->removeMessageInWorkingList($message);

        if ($this->eventCallback) {
            ($this->eventCallback)(new ConsumerEvent(ConsumerEvent::ACK_EVENT, $nbMessageAck, $this->getWorkingList()));
        }

        return $nbMessageAck;
    }

    /**
     * @return int the number of messages acked
     */
    public function ackAll(): int
    {
        $nbMessageAck = $this->removeList($this->getWorkingList());

        if ($this->eventCallback) {
            ($this->eventCallback)(new ConsumerEvent(ConsumerEvent::ACK_EVENT, $nbMessageAck, $this->getWorkingList()));
        }

        return $nbMessageAck;
    }

    /**
     * @param MessageEnvelope $message
     *
     * @return int the number of messages unack
     */
    public function unack(MessageEnvelope $message): int
    {
        $queueList = $this->queue->getARandomListName();

        if ($nbMessageUnack = $this->removeMessageInWorkingList($message)) {
            $this->redisClient->lpush($queueList, $message->getSerializedValue());
        }

        if ($this->eventCallback) {
            ($this->eventCallback)(new ConsumerEvent(ConsumerEvent::UNACK_EVENT, $nbMessageUnack, $queueList));
        }

        return $nbMessageUnack;
    }

    /**
     * @return int the number of messages unack
     */
    public function unackAll(): int
    {
        $queueList = $this->queue->getARandomListName();
        $nbMessageUnack = 0;

        do {
            $message = $this->redisClient->rpoplpush($this->getWorkingList(), $queueList);
            ++$nbMessageUnack;
        } while (!is_null($message));

        if ($this->eventCallback) {
            ($this->eventCallback)(new ConsumerEvent(ConsumerEvent::UNACK_EVENT, $nbMessageUnack, $queueList));
        }

        return $nbMessageUnack;
    }

    public function getQueueListsLength(): array
    {
        $queueListsLength = [];
        foreach ($this->queue->getQueueLists($this->redisClient) as $queueName) {
            $queueListsLength[$queueName] = $this->redisClient->llen($queueName);
        }

        return $queueListsLength;
    }

    /**
     * the working list has to be unique per consumer worker
     */
    protected function getWorkingList(): string
    {
        return $this->queue->getWorkingListPrefixName().'-'.$this->uniqueId;
    }

    /**
     * @param MessageEnvelope $message
     *
     * @return int the number of messages deleted
     */
    protected function removeMessageInWorkingList(MessageEnvelope $message)
    {
        $serializedMessage = $message->getSerializedValue();

        return $this->redisClient->lrem($this->getWorkingList(), 0, $serializedMessage);
    }

    /**
     * @param string $list
     *
     * @return int nb of elements in deleted list
     */
    protected function removeList(string $list)
    {
        $this->redisClient->multi();
        $this->redisClient->llen($list);
        $this->redisClient->del($list);
        $result = $this->redisClient->exec();

        return $result[0] ?? 0;
    }
}
