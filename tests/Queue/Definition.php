<?php

namespace M6Web\Component\RedisMessageBroker\Queue\tests\units;

use \mageekguy\atoum;

class Definition extends atoum\test
{
    public function testGetListNames()
    {
        $this
            ->if($t = $this->newTestedInstance('raoul', 4))
            ->then
                ->array($l = $t->getListNames())
                ->isIdentiCalTo(
                    ['raoul-1', 'raoul-2', 'raoul-3', 'raoul-4' ]
                )
            ;
    }

    public function testConstructor()
    {
        $this
            ->exception(
                function () {
                    $this->newTestedInstance('raoul', 0);
                }
            )->isInstanceOf('\InvalidArgumentException');

        $this
            ->exception(
                function () {
                    $this->newTestedInstance('', 2);
                }
            )->isInstanceOf('\InvalidArgumentException');
    }
}

