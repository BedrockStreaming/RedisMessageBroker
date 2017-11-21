<?php

namespace M6Web\Component\RedisMessageBroker\LoggedPredisClient;

use Predis\ClientInterface;

interface PredisClientInterface extends ClientInterface
{
    public function getClientFor($connectionID);

    public function quit();

    public function isConnected();

    public function getConnectionById($connectionID);

    public function executeRaw(array $arguments, &$error = null);

    public function pipeline();

    public function transaction();

    public function pubSubLoop();

    public function monitor();

    public function getIterator();
}
