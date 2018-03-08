<?php

namespace M6Web\Component\RedisMessageBroker\MessageHandler\tests\units;

use \mageekguy\atoum;
use M6Web\Component\RedisMessageBroker\Queue\Inspector;
use M6Web\Component\RedisMessageBroker\Queue\Definition;
use M6Web\Component\RedisMessageBroker\MessageEnvelope;
use \M6Web\Component\RedisMessageBroker\MessageHandler\Producer;
use \M6Web\Component\RedisMessageBroker\MessageHandler\Consumer;

class LostMessagesConsumer extends atoum\test
{
    public function testGrabbingOldMessage()
    {
        $this
            ->if(
                $queue = new Definition('queue4'.uniqid('test_redis', false)), // unique queue with a lot of list
                $pRedisClient = $this->getPredisMock(),
                $producer = new Producer($queue, $pRedisClient),
                $message = new MessageEnvelope(uniqid(), 'message in the bottle'),
                $producer->publishMessage($message), // publish a message
                $inspector = new Inspector($queue, $pRedisClient),

                $consumer = new Consumer($queue, $pRedisClient, 'testConsumer2'.uniqid('test_redis', false)),
                $consumer->setNoAutoAck(),

                $consumer->getMessageEnvelope()
            )
            ->then(
                sleep(10), // build an 10s old message
                $lostMessagesConsumer = new \M6Web\Component\RedisMessageBroker\MessageHandler\LostMessagesConsumer($queue, $pRedisClient, 3600),
                $lostMessagesConsumer->requeueOldMessages()
            )
            ->then
                ->integer($inspector->countReadyMessages())
                    ->isEqualTo(0)
                ->integer($inspector->countInProgressMessages()) // still in the working list
                    ->isEqualTo(1)
            ->if(
                $lostMessagesConsumer = new \M6Web\Component\RedisMessageBroker\MessageHandler\LostMessagesConsumer($queue, $pRedisClient, 1),
                $lostMessagesConsumer->requeueOldMessages()
            ) // consider now just 5s to get an old message
            ->then
                ->integer($inspector->countReadyMessages())
                    ->isEqualTo(1)
                ->integer($inspector->countInProgressMessages()) // still in the working list
                    ->isEqualTo(0)
        ;
    }

    public function testRetryMessageList()
    {
        $this
            ->if(
                $queue = new Definition('queue-testRetryMessageList'.uniqid('test_redis', false)), // unique queue with a lot of list
                $pRedisClient = $this->getPredisMock(),
                $producer = new Producer($queue, $pRedisClient),

                $message = new MessageEnvelope(uniqid(), 'message in the bottle'),

                $producer->publishMessage($message), // publish a message
                $inspector = new Inspector($queue, $pRedisClient),

                $consumer = new Consumer($queue, $pRedisClient, 'testConsumer2'.uniqid('test_redis', false)),
                $consumer->setNoAutoAck(),
                $consumer->getMessageEnvelope()
            )
            ->then(
                sleep(2), // build an 2s old message, retry set to 1
                $lostMessagesConsumer = new \M6Web\Component\RedisMessageBroker\MessageHandler\LostMessagesConsumer($queue, $pRedisClient, 3600, 1),
                $lostMessagesConsumer->requeueOldMessages()
            )
            ->then
            ->integer($inspector->countReadyMessages()) // one message is ready.
                ->isEqualTo(0)
            ->integer($inspector->countInProgressMessages()) // still in the working list
                ->isEqualTo(1)
            ->integer($inspector->countInErrorMessages()) // No message in deadLetterlist
                ->isEqualTo(0)
        ;
    }

    // Check more messages.
    public function testAllOldMessagesAreRequeue()
    {
        $this
            ->if(
                $queue = new Definition('queue-testAllOldMessagesAreRequeue'.uniqid('test_redis', false)), // unique queue with a lot of list
                $pRedisClient = $this->getPredisMock(),
                $producer = new Producer($queue, $pRedisClient),

                $producer->publishMessage(new MessageEnvelope(uniqid(), 'message in the bottle')), // publish a first message
                $producer->publishMessage(new MessageEnvelope(uniqid(), 'message in the bottlea')), // publish a second message
                $producer->publishMessage(new MessageEnvelope(uniqid(), 'message in the bottleb')), // publish a third message
                $producer->publishMessage(new MessageEnvelope(uniqid(), 'message in the bottlec')), // publish a fourth message

                $inspector = new Inspector($queue, $pRedisClient),

                $consumer = new Consumer($queue, $pRedisClient, 'testConsumer2'.uniqid('test_redis', false)),
                $consumer->setNoAutoAck(),

                $consumer->getMessageEnvelope(),
                $consumer->getMessageEnvelope(),
                $consumer->getMessageEnvelope(),
                $consumer->getMessageEnvelope()
            )
            ->then(
                sleep(5), // build an 5s old message
                $lostMessagesConsumer = new \M6Web\Component\RedisMessageBroker\MessageHandler\LostMessagesConsumer($queue, $pRedisClient, 3600, 1),
                $lostMessagesConsumer->requeueOldMessages()
            )
            ->then
            ->integer($inspector->countReadyMessages())
            ->isEqualTo(0)
            ->integer($inspector->countInProgressMessages()) // still in the working list
            ->isEqualTo(4)
        ;
    }

    public function testDeadLetterList()
    {
        $this
            ->if(
                $queue = new Definition('queue-testDeadLetterList'.uniqid('test_redis', false)), // unique queue with a lot of list
                $pRedisClient = $this->getPredisMock(),
                $producer = new Producer($queue, $pRedisClient),

                $message = new MessageEnvelope(uniqid(), 'message in the bottle'),
                $message->incrementRetry(),

                $producer->publishMessage($message), // publish a message
                $inspector = new Inspector($queue, $pRedisClient),

                $consumer = new Consumer($queue, $pRedisClient, 'testConsumer2'.uniqid('test_redis', false)),
                $consumer->setNoAutoAck(),
                $consumer->getMessageEnvelope()
            )
            ->then(
                sleep(2), // build an 2s old message, retry set to 1
                $lostMessagesConsumer = new \M6Web\Component\RedisMessageBroker\MessageHandler\LostMessagesConsumer($queue, $pRedisClient, 1, 1),
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

    /**
     * Get mock of the redis Client and define a default profile
     *
     * @return \M6Web\Component\RedisMock\RedisMock
     */
    protected function getPredisMock()
    {
        $factory = new \M6Web\Component\RedisMock\RedisMockFactory();
        $myRedisMock = $factory->getAdapter(\Predis\Client::class, false, false, '', [null, ['profile' => 'default']]);
        $myRedisMock->reset();

        return $myRedisMock;
    }
}
