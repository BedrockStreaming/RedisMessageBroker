<?php

namespace M6Web\Component\RedisMessageBroker\MessageHandler\tests\units;

use \mageekguy\atoum;

use M6Web\Component\RedisMessageBroker\MessageHandler\Producer;
use M6Web\Component\RedisMessageBroker\Queue\Definition;
use M6Web\Component\RedisMessageBroker\Message;

class Consumer extends atoum\test
{
    public function testGetMessage()
    {
        $this
            ->if(
                $queue = new Definition('queue1'.uniqid('test_redis', false)), // unique queue
                $redisClient = new \mock\Predis\Client(),
                $producer = new Producer($queue, $redisClient),
                $message = new Message('message in the bottle'),
                $producer->publishMessage($message)
            )
            ->and(
                $consumer = $this->newTestedInstance($queue, $redisClient, 'testConsumer'.uniqid('test_redis', false))
            )
            ->then
                ->object($newMessage = $consumer->getMessage())
            ->and
                ->string($newMessage->getMessage())
                ->isIdenticalTo($message->getMessage())
            ->and
                ->object($newMessage->getCreatedAt())
                ->isInstanceOf('Datetime')
                ->isEqualTo($message->getCreatedAt())
        ;
    }
}