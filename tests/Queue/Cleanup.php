<?php

namespace M6Web\Component\RedisMessageBroker\Queue\tests\units;

use M6Web\Component\RedisMessageBroker\MessageHandler\Consumer;
use M6Web\Component\RedisMessageBroker\MessageHandler\LostMessagesConsumer;
use M6Web\Component\RedisMessageBroker\Queue\Definition;
use M6Web\Component\RedisMessageBroker\Queue\Inspector;
use \mageekguy\atoum;

use M6Web\Component\RedisMessageBroker\MessageHandler\Producer;
use M6Web\Component\RedisMessageBroker\MessageEnvelope;

class Cleanup extends atoum\test
{
    public function testCleanWorkingListsOldMessages()
    {
        $this
            ->if(
                $queue = new Definition('queue1'.uniqid('test_redis', false)),
                $redisClient = new \Predis\Client(),
                $producer = new Producer($queue, $redisClient),
                $message = new MessageEnvelope(uniqid(), 'message in the bottle 1'),
                $message2 = new MessageEnvelope(uniqid(), 'message in the bottle 2'),
                $producer->publishMessage($message),
                $producer->publishMessage($message2),
                $inspector = new Inspector($queue, $redisClient),
                $cleanup = $this->newTestedInstance($queue, $redisClient)
            )
            ->and(
                sleep(4) // build two 4s old message
            )
            ->then
                ->integer($inspector->countReadyMessages())
                ->isEqualTo(2)

            ->and
                ->integer($cleanup->cleanWorkingListsOldMessages(2, true))
                ->isEqualTo(2) // two messages cleaned
            ->and
                ->integer($inspector->countReadyMessages())
                ->isEqualTo(0)
        ;

        $this
            ->if(
                $queue = new Definition('queue2'.uniqid('test_redis', false)),
                $redisClient = new \Predis\Client(),
                $producer = new Producer($queue, $redisClient),
                $message = new MessageEnvelope(uniqid(), 'message in the bottle 1'),
                $message2 = new MessageEnvelope(uniqid(), 'message in the bottle 2'),
                $producer->publishMessage($message),
                $producer->publishMessage($message2),
                $inspector = new Inspector($queue, $redisClient),
                $cleanup = $this->newTestedInstance($queue, $redisClient),
                $consumer = new Consumer($queue, $redisClient, uniqid('test_consumer', false)),
                $consumer->setNoAutoAck()
            )
            ->and(
                $consumer->getMessageEnvelope(), // get one message
                sleep(4) // build two 4s old message
            )
            ->and
                ->integer($cleanup->cleanWorkingListsOldMessages(2)) // clean in progress messages
                ->isEqualTo(1) // one message cleaned
            ->and
                ->integer($inspector->countReadyMessages())
                ->isEqualTo(1) // ready message is still here
        ;
    }

    public function testCleanDeadLetterLists()
    {
        $this
            ->if(
                $queue = new Definition('queue1'.uniqid('test_redis', false)),
                $redisClient = new \Predis\Client(),
                $producer = new Producer($queue, $redisClient),

                $message = new MessageEnvelope(uniqid(), 'message in the bottle 1'),
                $message->incrementRetry(),
                $producer->publishMessage($message),

                $inspector = new Inspector($queue, $redisClient),

                $consumer = new Consumer($queue, $redisClient, 'testConsumer2'.uniqid('test_redis', false)),
                $consumer->setNoAutoAck(),
                $consumer->getMessageEnvelope(),


                sleep(2),
                $lostMessagesConsumer = new LostMessagesConsumer($queue, $redisClient, 1, 1),
                $lostMessagesConsumer->requeueOldMessages(),

                $cleanup = $this->newTestedInstance($queue, $redisClient)
            )
            ->then
                ->integer($inspector->countInErrorMessages())
                    ->isEqualTo(1)

            ->and
                ->integer($cleanup->cleanDeadLetterLists())
                    ->isEqualTo(1) // one message cleaned
            ->and
                ->integer($inspector->countReadyMessages())
                    ->isEqualTo(0)
                ->integer($inspector->countInProgressMessages())
                    ->isEqualTo(0)
        ;
    }
}