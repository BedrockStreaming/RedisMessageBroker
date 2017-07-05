# RedisMessageBroker

This component will help you to build a messages brocker system over a [redis](redis.io) backend. It will take advantage of the redis cluster capabilities with the possibility to shard messages into several redis lists while producing messages. Consumer, in no auto-ack mode, implements a working list to be sure not to loose any messages when processing fail. 
 
By design, producing is super fast (one Redis command) and consuming car be slow.
 
You should use it with Redis >= 2.8.

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

### inspector

Inspector methods allow you to count the messages in ready or prosessing in a queue.

```
use M6Web\Component\RedisMessageBroker;
use Predis\Client as PredisClient;

$redisClient = new PredisClient(); // refer to PredisDocumentation
$queue = new RedisMessageBroker\Queue\Definition('raoul');

$inspector = new RedisMessageBroker\Inspector($queue, $redisClient);
$countInProgress = $inspector->countInProgressMessages();
$countReady = $inspector->countReadyMessages();
```

### cleanup

Cleanup methods let you perform a cleanup in the message queue. Cleanup is very slow as all the message in the queue will be scanned. 

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
$consumer->setNoAutoAck();

$message = $consumer->getMessage();
if ($message) {
    // do something with the message
    $consumer->ack($message); // erase the message from the working list
}
```
