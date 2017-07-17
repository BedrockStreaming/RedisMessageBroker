<?php

declare(strict_types=1);

namespace M6Web\Component\RedisMessageBroker\MessageHandler;

use M6Web\Component\RedisMessageBroker\Message;
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

    public function getMessage(): ?Message
    {
        $lists = iterator_to_array($this->queue->getQueueLists($this->redisClient));
        shuffle($lists);

        // #1 autoack
        if ($this->autoAck) {
            foreach ($lists as $list) {
                if ($message = $this->redisClient->rpop($list)) {
                    return self::unserializeMessage($message);
                }
            }
        }

        // #2 no-autoack - messages have to be acked manually via ->ack(message)
        // anything in the working list ? if so grab one
        if ($message = $this->redisClient->rpoplpush($this->getWorkingList(), $this->getWorkingList())) {
            return self::unserializeMessage($message);
        }

        // wohaaa - nothing in the working list !

        // no-autoack - grab something on another working list. Retrieve from working list on another instance of Consumer
        foreach ($this->queue->getWorkingLists($this->redisClient) as $list) {
            if ($message = $this->redisClient->rpoplpush($list, $list)) {
                return self::unserializeMessage($message);
            }
        }

        // grab something in the queue and put it in the workinglist while returning the message
        foreach ($lists as $list) {
            if ($message = $this->redisClient->rpoplpush($list, $this->getWorkingList())) {
                // if $list is empty delete it
                if (!$this->redisClient->llen($list)) {
                    $this->redisClient->del($list);
                }

                return self::unserializeMessage($message);
            }
        }

        return null;
    }

    public static function unserializeMessage(string $message): Message
    {
        return Message::unserializeMessage($message);
    }

    /**
     * the working list has to be unique per consumer worker
     */
    protected function getWorkingList(): string
    {
        return $this->queue->getWorkingListPrefixName().'-'.$this->uniqueId;
    }

    /**
     * @param Message $message this message will be acked in the working lists
     *
     * @return int the number of messages acked
     */
    public function ack(Message $message): int
    {
        $r = 0;
        $serializedMessage = $message->getSerializedValue();
        foreach ($this->queue->getWorkingLists($this->redisClient) as $list) {
            // erase all elements equals to the serialization of the message
            $r += $this->redisClient->lrem($list, 0, $serializedMessage);
            // delete working list if empty
            if (!$this->redisClient->llen($list)) {
                $this->redisClient->del($list);
            }
            if ($r > 0) { // something got erased, stop looking
                return $r;
            }
        }

        return $r;
    }
}
