<?php

declare(strict_types=1);

namespace M6Web\Component\RedisMessageBroker;

class Message
{
    /**
     * @var \DateTime
     */
    private $createdAt;

    /**
     * @var string
     */
    private $message;


    /**
     * Message constructor.
     *
     * @param string         $message   the payload message
     * @param \DateTime|null $createdAt created date will be set to now if not provided
     */
    public function __construct(string $message, \DateTime $createdAt = null)
    {
        $this->message = $message;
        $this->createdAt = $createdAt;
        if (null === $this->createdAt) {
            $this->createdAt = new \DateTime();
        }
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getSerializedValue(): string
    {
        return serialize($this);
    }

    public static function unserializeMessage(string $message): Message
    {
        return unserialize($message, ['allowed_classes' => ['M6Web\Component\RedisMessageBroker\Message', 'Datetime']]);
    }
}
