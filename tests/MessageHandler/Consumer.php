<?php

namespace M6Web\Component\RedisMessageBroker\MessageHandler\tests\units;

use M6Web\Component\RedisMessageBroker\Queue\Inspector;
use \mageekguy\atoum;

use M6Web\Component\RedisMessageBroker\MessageHandler\Producer;
use M6Web\Component\RedisMessageBroker\Queue\Definition;
use M6Web\Component\RedisMessageBroker\MessageEnvelope;

class Consumer extends atoum\test
{
    public function testGetMessage()
    {
        $this
            ->if(
                $queue = new Definition('queue1'.uniqid('test_redis', false)), // unique queue
                $redisClient = new \Predis\Client(),
                $producer = new Producer($queue, $redisClient),
                $message = new MessageEnvelope(uniqid(), 'message in the bottle'),
                $producer->publishMessage($message)
            )
            ->and(
                $consumer = $this->newTestedInstance($queue, $redisClient, 'testConsumer'.uniqid('test_redis', false))
            )
            ->then
                // get the message produced and see if its the same
                ->object($newMessage = $consumer->getMessageEnvelope())
            ->and
                ->string($newMessage->getMessage())
                ->isIdenticalTo($message->getMessage())
            ->and
                ->object($newMessage->getCreatedAt())
                ->isInstanceOf('Datetime')
                ->isEqualTo($message->getCreatedAt())
        ;
    }

    public function testAck()
    {
        $this
            ->if(
                $queue = new Definition('queue2'.uniqid('test_redis', false)), // unique queue
                $redisClient = new \mock\Predis\Client(),
                $producer = new Producer($queue, $redisClient),
                $message = new MessageEnvelope(uniqid(), 'message in the bottle'),
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
                ->object($newMessage = $consumer->getMessageEnvelope())
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
            ->and($newMessage = $consumer->getMessageEnvelope())
            ->then(
                // a new consumer should NOT be able to get the message even if is not acked
                $consumer2 = $this->newTestedInstance($queue, $redisClient, 'testConsumer2'.uniqid('test_redis', false)),
                $consumer2->ack($message)
            )
            ->and
                ->integer($inspector->countReadyMessages())
                    ->isEqualTo(0)
                ->integer($inspector->countInProgressMessages())
                    ->isEqualTo(1)
            ;
    }

    public function testAckAll()
    {
        $this
            ->if(
                $queue = new Definition('queue2'.uniqid('test_redis', false)), // unique queue
                $redisClient = new \mock\Predis\Client(),

                // publish two messages
                $producer = new Producer($queue, $redisClient),
                $producer->publishMessage(new MessageEnvelope(uniqid(), 'message in the bottle')),
                $producer->publishMessage(new MessageEnvelope(uniqid(), 'message in the bottle2')),

                $consumer = $this->newTestedInstance($queue, $redisClient, 'testConsumer1'.uniqid('test_redis', false)),
                $consumer->setNoAutoAck(),

                $inspector = new Inspector($queue, $redisClient)
            )
            ->then
                ->object($consumer->getMessageEnvelope())
                ->object($consumer->getMessageEnvelope())

                // messages should not be in the queue but in the working list
                ->integer($inspector->countReadyMessages())
                    ->isEqualTo(0)
                ->integer($inspector->countInProgressMessages())
                    ->isEqualTo(2)
            ->and
                // ack messages
                ->integer($consumer->ackAll())
                // messages is gone
                ->integer($inspector->countReadyMessages())
                    ->isEqualTo(0)
                ->integer($inspector->countInProgressMessages())
                    ->isEqualTo(0)
        ;
    }

    public function testUnack()
    {
        $this
            ->if(
                $queue = new Definition('queue2'.uniqid('test_redis', false)), // unique queue
                $redisClient = new \mock\Predis\Client(),

                $producer = new Producer($queue, $redisClient),
                $producer->publishMessage(new MessageEnvelope(uniqid(), 'message in the bottle')),

                $consumer = $this->newTestedInstance($queue, $redisClient, 'testConsumer1'.uniqid('test_redis', false)),
                $consumer->setNoAutoAck(),

                $inspector = new Inspector($queue, $redisClient)
            )
            ->then
                ->object($message = $consumer->getMessageEnvelope())

                // messages should not be in the queue but in the working list
                ->integer($inspector->countReadyMessages())
                    ->isEqualTo(0)
                ->integer($inspector->countInProgressMessages())
                    ->isEqualTo(1)
            ->and
                // ack messages
                ->integer($consumer->unack($message))
                // messages is gone
                ->integer($inspector->countReadyMessages())
                    ->isEqualTo(1)
                ->integer($inspector->countInProgressMessages())
                    ->isEqualTo(0)
        ;
    }

    public function testUnackAll()
    {
        $this
            ->if(
                $queue = new Definition('queue2'.uniqid('test_redis', false)), // unique queue
                $redisClient = new \mock\Predis\Client(),

                // publish two messages
                $producer = new Producer($queue, $redisClient),
                $producer->publishMessage(new MessageEnvelope(uniqid(), 'message in the bottle')),
                $producer->publishMessage(new MessageEnvelope(uniqid(), 'message in the bottle2')),

                $consumer = $this->newTestedInstance($queue, $redisClient, 'testConsumer1'.uniqid('test_redis', false)),
                $consumer->setNoAutoAck(),

                $inspector = new Inspector($queue, $redisClient)
            )
            ->then
                ->object($consumer->getMessageEnvelope())
                ->object($consumer->getMessageEnvelope())

                // messages should not be in the queue but in the working list
                ->integer($inspector->countReadyMessages())
                ->isEqualTo(0)
                ->integer($inspector->countInProgressMessages())
                ->isEqualTo(2)
            ->and
                // ack messages
                ->integer($consumer->unackAll())
                // messages is gone
                ->integer($inspector->countReadyMessages())
                    ->isEqualTo(2)
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
                $message = new MessageEnvelope(uniqid(), 'message in the bottle'),
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
                $message = $consumer->getMessageEnvelope()
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

    public function testGetQueueListsLength()
    {
        $this
            ->if(
                $queueName = 'queue2'.uniqid('test_redis', false),
                $queue = new Definition($queueName),
                $redisClient = new \mock\Predis\Client(),

                // publish two messages
                $producer = new Producer($queue, $redisClient),
                $producer->publishMessage(new MessageEnvelope(uniqid(), 'message in the bottle')),
                $producer->publishMessage(new MessageEnvelope(uniqid(), 'message in the bottle2')),

                $consumer = $this->newTestedInstance($queue, $redisClient, 'testConsumer1'.uniqid('test_redis', false))
            )
            ->then
                ->array($queueListName = $consumer->getQueueListsLength())
                    ->hasSize(1)
                    ->hasKey($queueName.'_list__1')
                ->integer($queueListName[$queueName.'_list__1'])
                    ->isEqualTo(2)
        ;
    }
}