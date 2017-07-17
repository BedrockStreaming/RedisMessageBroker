<?php

namespace M6Web\Component\RedisMessageBroker\MessageHandler\tests\units;

use M6Web\Component\RedisMessageBroker\Queue\Inspector;
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
                $redisClient = new \Predis\Client(),
                $producer = new Producer($queue, $redisClient),
                $message = new Message('message in the bottle'),
                $producer->publishMessage($message)
            )
            ->and(
                $consumer = $this->newTestedInstance($queue, $redisClient, 'testConsumer'.uniqid('test_redis', false))
            )
            ->then
                // get the message produced and see if its the same
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

    /*
    public function testWorkingList()
    {

    }
    */

    public function testAck()
    {
        $this
            ->if(
                $queue = new Definition('queue2'.uniqid('test_redis', false)), // unique queue
                $redisClient = new \mock\Predis\Client(),
                $producer = new Producer($queue, $redisClient),
                $message = new Message('message in the bottle'),
                $producer->publishMessage($message)
            )
            ->and(
                $consumer = $this->newTestedInstance($queue, $redisClient, 'testConsumer1'.uniqid('test_redis', false)),
                $consumer->setNoAutoAck()
            )
            ->and(
                $inspector = new Inspector($queue, $redisClient)
            )
            ->then
                ->object($newMessage = $consumer->getMessage())
            ->and
                // message should not be in the queue but in the working list
                ->integer($inspector->countReadyMessages())
                    ->isEqualTo(0)
                ->integer($inspector->countInProgressMessages())
                    ->isEqualTo(1)
            ->then
                // ack message
                ->integer($consumer->ack($newMessage))
                // message is gone
                ->integer($inspector->countReadyMessages())
                    ->isEqualTo(0)
                ->integer($inspector->countInProgressMessages())
                    ->isEqualTo(0)
            ;

        $this
            ->if($producer->publishMessage($message))
            ->and($newMessage = $consumer->getMessage())
            ->then(
                // a new consumer should be able to get the message beacause is not acked
                $consumer2 = $this->newTestedInstance($queue, $redisClient, 'testConsumer2'.uniqid('test_redis', false)),
                $consumer2->ack($message)
            )
            ->and
                ->integer($inspector->countReadyMessages())
                    ->isEqualTo(0)
                ->integer($inspector->countInProgressMessages())
                    ->isEqualTo(0)
            ;
    }

    public function testConsumeOnMultipleList()
    {
        $this
            ->if(
                $queue = new Definition('queue3'.uniqid('test_redis', false), 10000), // unique queue with a lot of list
                $redisClient = new \Predis\Client(),
                $producer = new Producer($queue, $redisClient),
                $message = new Message('message in the bottle'),
                $producer->publishMessage($message),
                $inspector = new Inspector($queue, $redisClient)
            )
            ->then
                ->integer($inspector->countListQueues())
                    ->isEqualTo(1) // one list is created
                ->integer($inspector->countWorkingListQueues())
                    ->isEqualTo(0)
            ->if(
                $consumer = $this->newTestedInstance($queue, $redisClient, 'testConsumer3'.uniqid('test_redis', false)),
                $consumer->setNoAutoAck(),
                $message = $consumer->getMessage()
            )
            ->then
                ->integer($inspector->countListQueues())
                    ->isEqualTo(0)
                ->integer($inspector->countWorkingListQueues())
                    ->isEqualTo(1) // one working list is created
            ->if(
                $consumer->ack($message)
            )
            ->then
            ->integer($inspector->countListQueues())
                ->isEqualTo(0)
            ->integer($inspector->countWorkingListQueues())
                ->isEqualTo(0) // working list is empty so has been deleted
        ;
    }
}