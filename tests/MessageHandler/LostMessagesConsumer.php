<?php

namespace M6Web\Component\RedisMessageBroker\MessageHandler\tests\units;

use M6Web\Component\RedisMessageBroker\MessageHandler\Consumer;
use M6Web\Component\RedisMessageBroker\Queue\Inspector;
use \mageekguy\atoum;

use M6Web\Component\RedisMessageBroker\MessageHandler\Producer;
use M6Web\Component\RedisMessageBroker\Queue\Definition;
use M6Web\Component\RedisMessageBroker\MessageEnvelope;

class LostMessagesConsumer extends atoum\test
{
    public function testGrabbingOldMessage()
    {
        $this
            ->if(
                $queue = new Definition('queue4'.uniqid('test_redis', false)), // unique queue with a lot of list
                $redisClient = new \Predis\Client(),
                $producer = new Producer($queue, $redisClient),

                $message = new MessageEnvelope(uniqid(), 'message in the bottle'),
                $producer->publishMessage($message), // publish a message
                $inspector = new Inspector($queue, $redisClient),

                $consumer = new Consumer($queue, $redisClient, 'testConsumer2'.uniqid('test_redis', false)),
                $consumer->setNoAutoAck(),
                $consumer->getMessage()
            )
            ->then(
                sleep(10), // build an 20s old message
                $lostMessagesConsumer = $this->newTestedInstance($queue, $redisClient, 3600),
                $lostMessagesConsumer->requeueOldMessages()
            )
            ->then
                ->integer($inspector->countReadyMessages())
                ->isEqualTo(0)
                ->integer($inspector->countInProgressMessages()) // still in the working list
                ->isEqualTo(1)
            ->if(
                $lostMessagesConsumer = $this->newTestedInstance($queue, $redisClient, 5),
                $lostMessagesConsumer->requeueOldMessages()
            ) // consider now just 5s to get an old message
            ->then
                ->integer($inspector->countReadyMessages())
                    ->isEqualTo(1)
                ->integer($inspector->countInProgressMessages()) // still in the working list
                    ->isEqualTo(0)
        ;
    }

    public function testDeadLetterList()
    {
        $this
            ->if(
                $queue = new Definition('queue4'.uniqid('test_redis', false)), // unique queue with a lot of list
                $redisClient = new \Predis\Client(),
                $producer = new Producer($queue, $redisClient),

                $message = new MessageEnvelope(uniqid(), 'message in the bottle'),
                $message->incrementRetry(),

                $producer->publishMessage($message), // publish a message
                $inspector = new Inspector($queue, $redisClient),

                $consumer = new Consumer($queue, $redisClient, 'testConsumer2'.uniqid('test_redis', false)),
                $consumer->setNoAutoAck(),
                $consumer->getMessage()
            )
            ->then(
                sleep(2), // build an 2s old message, retry set to 1
                $lostMessagesConsumer = $this->newTestedInstance($queue, $redisClient, 1, 1),
                $lostMessagesConsumer->requeueOldMessages()
            )
            ->then
                ->integer($inspector->countReadyMessages())
                    ->isEqualTo(0)
                ->integer($inspector->countInProgressMessages()) // still in the working list
                    ->isEqualTo(0)
                ->integer($inspector->countInErrorMessages()) // max retry reached, stay in dead letter list
                    ->isEqualTo(1)
        ;
    }
}
