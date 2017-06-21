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
     * uniqueId of the message
     *
     * @var string
     */
    private $uniqueId;

    /**
     * @var int
     */
    private $consumptionAttempts = 0;

    /**
     * Message constructor.
     *
     * @param string         $message   the payload message
     * @param \DateTime|null $createdAt created date will be set to now if not provided
     * @param null           $uniqueId  uniqueId will be set via uniqid() if not provided
     */
    public function __construct(string $message, \DateTime $createdAt = null, $uniqueId = null)
    {
        $this->message;
        if (null === $createdAt) {
            $this->createdAt = new \DateTime();
        }
        if (null === $uniqueId) {
            $this->uniqueId = uniqid(__CLASS__, true);
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

    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    public function consumptionAttempt(): null
    {
        ++$this->consumptionAttempts;
    }

    public function getConsumptionAttemps(): int
    {
        return $this->consumptionAttempts;
    }

    public function getSerializedValue(): string
    {
        return serialize($this);
    }

    public static function unserializeMessage(string $message): Message
    {
        return unserialize($message, ['allowed_classes' => ['Message']]);
    }
}
