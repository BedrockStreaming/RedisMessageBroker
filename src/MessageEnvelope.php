<?php

declare(strict_types=1);

namespace M6Web\Component\RedisMessageBroker;

class MessageEnvelope
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var \DateTime
     */
    private $createdAt;

    /**
     * @var \DateTime
     */
    private $updatedAt;

    /**
     * @var string
     */
    private $message;

    /**
     * @var int
     */
    private $retry;

    /**
     * Message constructor.
     *
     * @param string         $id        message id
     * @param mixed          $message   the payload message
     * @param \DateTime|null $createdAt created date will be set to now if not provided
     */
    public function __construct(string $id, $message, \DateTime $createdAt = null)
    {
        $this->id = $id;
        $this->message = $message;
        $this->createdAt = $createdAt;
        if (null === $this->createdAt) {
            $this->createdAt = new \DateTime();
        }
        $this->updatedAt = $this->createdAt;
        $this->retry = 0;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setUpdatedAt(\DateTime $dateTime): self
    {
        $this->createdAt = $dateTime;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function incrementRetry(): self
    {
        $this->setUpdatedAt(new \DateTime());

        $this->retry++;

        return $this;
    }

    public function getRetry()
    {
        return $this->retry;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getSerializedValue(): string
    {
        return serialize($this);
    }

    public static function unserializeMessage(string $message): ?self
    {
        $unserializeMessage = unserialize($message);

        return ($unserializeMessage instanceof self) ? $unserializeMessage : null;
    }
}
