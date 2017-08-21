<?php

declare(strict_types=1);

namespace M6Web\Component\RedisMessageBroker\Event;

class ConsumerEvent
{
    const ACK_EVENT = 'ack';
    const UNACK_EVENT = 'unack';
    const REQUEUE_OLD_MESSAGE = 'requeue_old_message';
    const MAX_RETRY_REACHED = 'max_retry_reached';

    /**
     * @var string
     */
    protected $eventName;

    /**
     * @var int
     */
    protected $nbMessage;

    /**
     * @var string
     */
    protected $listName;

    public function __construct(string $eventName, int $nbMessage, string $listName)
    {
        $this->eventName = $eventName;
        $this->nbMessage = $nbMessage;
        $this->listName = $listName;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function getNbMessage(): int
    {
        return $this->nbMessage;
    }

    public function getListName(): string
    {
        return $this->listName;
    }
}
