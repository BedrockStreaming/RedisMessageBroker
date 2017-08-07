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
     * number of seconds to check before a message was considered old in a working list
     * leave it to zero to deactivate
     *
     * @var int
     */
    protected $timeOldMessage = 0;

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

    public function setTimeOldMessage(int $v): void
    {
        $this->timeOldMessage = $v;
    }

    public function getMessage(): ?Message
    {
        $messages = $this->getMessages(1);

        return !$messages->isEmpty() ? $messages->shift() : null;
    }

    /**
     * @param int $limit
     *
     * @return \SplStack
     */
    public function getMessages(int $limit): \SplStack
    {
        $lists = iterator_to_array($this->queue->getQueueLists($this->redisClient));
        shuffle($lists);

        // #1 autoack
        if ($this->autoAck) {
            return $this->getMessageFromQueueLists($lists, $limit);
        }

        // #2 no-autoack - messages have to be acked manually via ->ack(message)
        return $this->getMessagesFromWorkingLists($lists, $limit);
    }

    public static function unserializeMessage(string $message): Message
    {
        return Message::unserializeMessage($message);
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

    /**
     * the working list has to be unique per consumer worker
     */
    protected function getWorkingList(): string
    {
        return $this->queue->getWorkingListPrefixName().'-'.$this->uniqueId;
    }

    /**
     * @param array $lists
     * @param int   $nbMessage
     *
     * @return \SplStack
     */
    protected function getMessageFromQueueLists(array $lists, int $nbMessage): \SplStack
    {
        $messages = new \SplStack();

        foreach ($lists as $list) {
            while ($messages->count() < $nbMessage && $message = $this->redisClient->rpop($list)) {
                $messages->push(self::unserializeMessage($message));
            }

            if ($messages->count() >= $nbMessage) {
                break;
            }
        }

        return $messages;
    }

    /**
     * messages have to be acked manually via ->ack(message)
     *
     * - Get message from working list
     *
     * - grab something on another working list. Retrieve from working list on another instance of Consumer
     * this is done only if the message is old enough to be considered as lost
     *
     * - get messages from queue list and add them in working queue
     *
     * @param array $lists
     * @param int   $nbMessage
     *
     * @return \SplStack
     */
    protected function getMessagesFromWorkingLists(array $lists, int $nbMessage): \SplStack
    {
        $messages = new \SplStack();

        // anything in the working list ? if so grab element stay on working list (not ack)
        foreach ($this->redisClient->lrange($this->getWorkingList(), 0, $nbMessage - 1) as $message) {
            $messages->push(self::unserializeMessage($message));
        }

        // wohaaa - nothing in the working list !
        // grab something on another working list. Retrieve from working list on another instance of Consumer
        // this is done only if the message is old enough to be considered as lost
        if ($this->timeOldMessage && $messages->count() < $nbMessage) {
            foreach ($this->queue->getWorkingLists($this->redisClient) as $list) {
                foreach ($this->redisClient->lrange($list, 0, ($nbMessage - $messages->count() - 1)) as $message) {
                    $message = self::unserializeMessage($message);
                    if ((time() - $message->getCreatedAt()->format('U')) > $this->timeOldMessage) {
                        $messages->push($message);
                    }
                }
            }
        }

        // grab something in the queue and put it in the workinglist while returning the message
        foreach ($lists as $list) {
            while ($messages->count() < $nbMessage && $message = $this->redisClient->rpoplpush($list, $this->getWorkingList())) {
                $messages->push(self::unserializeMessage($message));
            }

            if ($messages->count() >= $nbMessage) {
                break;
            }

            // if $list is empty delete it
            if (!$this->redisClient->llen($list)) {
                $this->redisClient->del($list);
            }
        }

        return $messages;
    }
}
