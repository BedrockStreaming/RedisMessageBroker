# RedisMessageBroker

WIP 

## usage 

### producer

```
<?php
use M6Web\Component\RedisMessageBroker;
use Predis\Client as PredisClient;

$redisClient = new PredisClient(); // refer to PredisDocumentation
$queue = new RedisMessageBroker\Queue\Definition('raoul');
$message = new RedisMessageBroker\Message('un message');

$producer = new RedisMessageBroker\MessageHandler\Producer($queue, $redisClient);
$producer->publishMessage($message);

```

### consumer

Consumer should be wrapped in a worker. A unique Id should be pass to the consumer constructor. If you work with a worker, uniqId has to be constant per worker.

```
<?php
use M6Web\Component\RedisMessageBroker;
use Predis\Client as PredisClient;

$redisClient = new PredisClient(); // refer to PredisDocumentation
$queue = new RedisMessageBroker\Queue\Definition('raoul');
$consumer = new RedisMessageBroker\MessageHandler\Consumer($squeue, $redisClient, uniqid());

$message = $consumer->getMessage();

```

## queue option

To avoid hotpsots (when using redis in a cluster) you can shard a queue on several lists : 

```
$queue = new RedisMessageBroker\Queue\Definition('raoul', 10); // shard on 10 lists
```

In this mode, messages will be written and read among the 10 lists. FIFO is no more guaranteed.

## manual message acknowledgment

```
use M6Web\Component\RedisMessageBroker;
use Predis\Client as PredisClient;

$redisClient = new PredisClient(); // refer to PredisDocumentation
$queue = new RedisMessageBroker\Queue\Definition('raoul');
$consumer = new RedisMessageBroker\MessageHandler\Consumer($squeue, $redisClient, uniqid());

$message = $consumer->getMessage();
// do something
$consumer->ack($message);
```
