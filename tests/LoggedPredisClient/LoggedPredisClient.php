<?php

namespace M6Web\Component\RedisMessageBroker\LoggedPredisClient\tests\units;

use \mageekguy\atoum;

class LoggedPredisClient extends atoum\test
{
    public function testDel()
    {
        $this
            ->if(
                $predisClient = $this->getPredisClientMock(),
                $loggedPredisClient = $this->newTestedInstance($predisClient)
            )
            ->when($response = $loggedPredisClient->del(['some', 'keys']))
            ->then
                ->mock($predisClient)->call('del')->once();
    }

    public function testExec()
    {
        $this
            ->if(
                $predisClient = $this->getPredisClientMock(),
                $loggedPredisClient = $this->newTestedInstance($predisClient)
            )
            ->when($response = $loggedPredisClient->exec())
            ->then
                ->mock($predisClient)->call('exec')->once();
    }

    public function testLlen()
    {
        $this
            ->if(
                $predisClient = $this->getPredisClientMock(),
                $loggedPredisClient = $this->newTestedInstance($predisClient)
            )
            ->when($response = $loggedPredisClient->llen('key'))
            ->then
                ->mock($predisClient)->call('llen')->once();
    }

    public function testLpush()
    {
        $this
            ->if(
                $predisClient = $this->getPredisClientMock(),
                $loggedPredisClient = $this->newTestedInstance($predisClient)
            )
            ->when($response = $loggedPredisClient->lpush('key', ['some', 'values']))
            ->then
                ->mock($predisClient)->call('lpush')->once();
    }

    public function testLrem()
    {
        $this
            ->if(
                $predisClient = $this->getPredisClientMock(),
                $loggedPredisClient = $this->newTestedInstance($predisClient)
            )
            ->when($response = $loggedPredisClient->lrem('key', 100, 'value'))
            ->then
                ->mock($predisClient)->call('lrem')->once();
    }

    public function testMulti()
    {
        $this
            ->if(
                $predisClient = $this->getPredisClientMock(),
                $loggedPredisClient = $this->newTestedInstance($predisClient)
            )
            ->when($response = $loggedPredisClient->multi())
            ->then
                ->mock($predisClient)->call('multi')->once();
    }

    public function testRpop()
    {
        $this
            ->if(
                $predisClient = $this->getPredisClientMock(),
                $loggedPredisClient = $this->newTestedInstance($predisClient)
            )
            ->when($response = $loggedPredisClient->rpop('key'))
            ->then
                ->mock($predisClient)->call('rpop')->once();
    }

    public function testRpoplpush()
    {
        $this
            ->if(
                $predisClient = $this->getPredisClientMock(),
                $loggedPredisClient = $this->newTestedInstance($predisClient)
            )
            ->when($response = $loggedPredisClient->rpoplpush('source', 'destination'))
            ->then
                ->mock($predisClient)->call('rpoplpush')->once();
    }

    protected function getPredisClientMock()
    {
        $this->mockGenerator->orphanize('__construct');
        $predisClientMock = new \mock\Predis\Client();
        $predisClientMock->getMockController()->del = 1;
        $predisClientMock->getMockController()->exec = [];
        $predisClientMock->getMockController()->llen = 1;
        $predisClientMock->getMockController()->lpush = 1;
        $predisClientMock->getMockController()->lrem = 1;
        $predisClientMock->getMockController()->multi = [];
        $predisClientMock->getMockController()->rpop = 'something';
        $predisClientMock->getMockController()->rpoplpush = 'something';

        return $predisClientMock;
    }

}
