<?php

namespace M6Web\Component\RedisMessageBroker\Queue\tests\units;

use M6Web\Component\RedisMessageBroker\MessageHandler\Consumer;
use \mageekguy\atoum;


use M6Web\Component\RedisMessageBroker\Queue\{Definition, Inspector};

use M6Web\Component\RedisMessageBroker\MessageHandler\Producer;
use M6Web\Component\RedisMessageBroker\Message;

class Cleanup extends atoum\test
{
    public function testCleanOldMessages()
    {
        $this
            ->if(
                $queue = new Definition('queue1'.uniqid('test_redis', false)),
                $redisClient = new \Predis\Client(),
                $producer = new Producer($queue, $redisClient),
                $message = new Message('message in the bottle 1'),
                $message2 = new Message('message in the bottle 2'),
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
                ->integer($cleanup->cleanOldMessages(2, true))
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
                $message = new Message('message in the bottle 1'),
                $message2 = new Message('message in the bottle 2'),
                $producer->publishMessage($message),
                $producer->publishMessage($message2),
                $inspector = new Inspector($queue, $redisClient),
                $cleanup = $this->newTestedInstance($queue, $redisClient),
                $consumer = new Consumer($queue, $redisClient, uniqid('test_consumer', false)),
                $consumer->setNoAutoAck()
            )
            ->and(
                $consumer->getMessage(), // get one message
                sleep(4) // build two 4s old message
            )
            ->and
                ->integer($cleanup->cleanOldMessages(2)) // clean in progress messages
                ->isEqualTo(1) // one message cleaned
            ->and
                ->integer($inspector->countReadyMessages())
                ->isEqualTo(1) // ready message is still here
        ;
    }

}