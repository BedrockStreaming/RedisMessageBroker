<?php

declare(strict_types=1);

namespace M6Web\Component\RedisMessageBroker\MessageHandler;

use M6Web\Component\RedisMessageBroker\Message;
use M6Web\Component\RedisMessageBroker\Queue;

/**
 * Class Consumer
 *
 * Consume messages
 * two modes are available : auto-ack or no-ack
 * auto-ack mode will directly fech and erase the message from the queue
 * no-ack mode will fetch the the message in a list unique for the worker. You will have to ack the message to erase it of the process list
 *
 * working list as to be unique
 */
class Consumer extends AbstractMessageHandler
{
    private $consumerId;

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
        parent::_construct($queue, $redisClient);
    }

    public function setNoAutoAck()
    {
        $this->autoAck = false;
    }

    public function getMessage(): ?Message
    {
        $lists = $this->queue->getListNames();
        shuffle($lists);

        // autoack
        if ($this->autoAck) {
            foreach ($lists as $list) {
                if ($message = $this->redisClient->rpop($list)) {
                    return self::unserializeMessage($message);
                }
            }
        }

        // no-autoack - messages have to be acked manually via ack
        // anything in the working list ? if so grab one
        if ($this->redisClient->llen($this->getWorkingList())) {
            // fetch from working list to see if there is something left to do
            return self::unserializeMessage(
                $this->redisClient->rpoplpush($this->getWorkingList(), $this->getWorkingList())
            );
        }

        // wohaaa - nothing in the working list !

        // no-autoack - grab something on another working list. Retrieve from working list on another instance of Consumer
        $workingLists = $this->redisClient->keys($this->queue->getWorkingListPrefixName().'*');
        shuffle($workingLists);
        foreach ($workingLists as $list) {
            if ($message = $this->redisClient->rpoplpush($list, $list)) {
                return self::unserializeMessage($message);
            }
        }

        // grab something in the queue and put it in the workinglist while returning the message
        foreach ($lists as $list) {
            if ($message = $this->redisClient->rpoplpush($list, $this->getWorkingList())) {
                return self::unserializeMessage($message);
            }
        }

        return null;
    }

    public static function unserializeMessage(string $message): Message
    {
        $message = Message::unserializeMessage($message);

        return $message;
    }

    /**
     * the working list has to be unique per consumer worker
     */
    protected function getWorkingList(): string
    {
        return $this->queue->getWorkingListPrefixName().'-'.$this->uniqueId;
    }

    /**
     * @return int the number of messages acked
     */
    public function ack(Message $message): int
    {
        // erase all elements equals to the serialization of the message
        if ($r = $this->redisClient->lrem($this->getWorkingList(), 0, $message->getSerializedValue())) {
            // delete working list if empty
            if (!$this->redisClient->llen($this->getWorkingList())) {
                $this->redisClient->del($this->getWorkingList());
            }
        }

        return $r;
    }
}
