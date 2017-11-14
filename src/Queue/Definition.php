<?php

declare(strict_types=1);

namespace M6Web\Component\RedisMessageBroker\Queue;

use Predis\ClientInterface;
use Predis\Collection\Iterator;

/**
 * Class Definition
 * Define a queue and allow to get a list of physical redis lists
 */
class Definition
{
    /**
     * @var int number of physical redis list created
     */
    private $listCount;

    /**
     * @var string name of the topic
     */
    private $name;

    /**
     * Definition constructor.
     *
     * @param string $name      name of the queue
     * @param int    $listCount number of physical redis list to create. just one per default
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $name, int $listCount = 1)
    {
        if (empty($name) or empty($listCount)) {
            throw new \InvalidArgumentException('name nor listCount cannot be empty');
        }
        $this->name = $name;
        $this->listCount = abs($listCount);
    }

    /**
     * get physical redis list names
     */
    public function getListNames(): array
    {
        $t = range(1, $this->listCount);
        array_walk(
            $t, function (&$v) {
                $v = $this->getListPrefixName().'_'.$v;
            }
        );

        return $t;
    }

    public function getARandomListName(): string
    {
        return $this->getListNames()[array_rand($this->getListNames())];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getListPrefixName(): string
    {
        return $this->name.'_list_';
    }

    public function getWorkingListPrefixName(): string
    {
        return $this->name.'_working_list_';
    }

    public function getDeadLetterListName(): string
    {
        return $this->name.'_dead_letter_list_';
    }

    /**
     * scan the database to get the list of working list for the queue
     *
     * @param ClientInterface $client
     *
     * @return \Iterator
     */
    public function getWorkingLists(ClientInterface $client): \Iterator
    {
        return new Iterator\Keyspace($client, $this->getWorkingListPrefixName().'*'); // SCAN the database
    }

    public function getQueueLists(ClientInterface $client): \Iterator
    {
        return new Iterator\Keyspace($client, $this->getListPrefixName().'*'); // SCAN the database
    }

    public function getDeadLetterLists(ClientInterface $client): \Iterator
    {
        return new Iterator\Keyspace($client, $this->getDeadLetterListName().'*'); // SCAN the database
    }
}
