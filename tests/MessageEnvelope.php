<?php

namespace M6Web\Component\RedisMessageBroker\tests\units;

use M6Web\Component\RedisMessageBroker\MessageEnvelope as Base;
use \mageekguy\atoum;


class MessageEnvelope extends atoum\test
{

    public function testBasics() {
        $this
            ->if($t = $this->newTestedInstance(uniqid(), 'raoul the message', \DateTime::createFromFormat('Y-m-d H:i:s', '2017-07-10 14:05:00')))
            ->then
                ->object($t->getCreatedAt())
                ->isInstanceOf('DateTime')
                ->isEqualTo(\DateTime::createFromFormat('Y-m-d H:i:s', '2017-07-10 14:05:00'))
            ->and
                ->string($t->getMessage())
                ->isEqualTo('raoul the message')
            ;
    }

    public function testSerialize($message)
    {
        $this
            ->if($messageEnvelope = $this->newTestedInstance(uniqid(), $message))
            ->then
                ->string($serializedValue = $messageEnvelope->getSerializedValue())
            ->and
                ->object($transportedMessage = Base::unserializeMessage($serializedValue))
                    ->isEqualTo($messageEnvelope)
            ;
    }

    public function testUnSerializeBadMessage()
    {
        $this
            ->if($message = serialize(new \DateTime()))
            ->then
                ->variable($transportedMessage = Base::unserializeMessage($message))
                    ->isNull()
        ;
    }

    protected function testSerializeDataProvider()
    {
       return [
           [
               'raoul the message'
           ],
           [
               new \SplStack()
           ]
       ];
    }
}
