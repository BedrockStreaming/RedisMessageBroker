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
                    ['raoul_list__1', 'raoul_list__2', 'raoul_list__3', 'raoul_list__4' ]
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

