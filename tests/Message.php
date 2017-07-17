<?php


namespace M6Web\Component\RedisMessageBroker\tests\units;


use M6Web\Component\RedisMessageBroker\Message as Base;
use \mageekguy\atoum;


class Message extends atoum\test
{

    public function testBasics() {
        $this
            ->if($t = $this->newTestedInstance('raoul the message', \DateTime::createFromFormat('Y-m-d H:i:s', '2017-07-10 14:05:00')))
            ->then
                ->object($t->getCreatedAt())
                ->isInstanceOf('DateTime')
                ->isEqualTo(\DateTime::createFromFormat('Y-m-d H:i:s', '2017-07-10 14:05:00'))
            ->and
                ->string($t->getMessage())
                ->isEqualTo('raoul the message')
            ;
    }

    public function testSerialize()
    {
        $this
            ->if(
                $message = $this->newTestedInstance('raoul the message'),
                $date = $message->getCreatedAt(),
                $stringMessage = $message->getMessage()
                )
            ->then
                ->string($serializedValue = $message->getSerializedValue())
            ->and
                ->object($transportedMessage = Base::unserializeMessage($serializedValue))
                ->isEqualTo($message)
            ;
    }

}