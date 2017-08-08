# RedisMessageBroker

This component will help you to build a messages brocker system over a [redis](redis.io) backend. It will take advantage of the redis cluster capabilities with the possibility to shard messages into several redis lists while producing messages. Consumer, in no auto-ack mode, implements a working list to be sure not to loose any messages when processing fail. 
 
By design, producing is super fast (one Redis command) and consuming can be slow if you want to ack messages manually.
 
You should use it with Redis >= 2.8.

## usage 

### producer

```php
<?php
use M6Web\Component\RedisMessageBroker;
use Predis\Client as PredisClient;

$redisClient = new PredisClient(); // refer to PredisDocumentation
$queue = new RedisMessageBroker\Queue\Definition('raoul');
$message = new RedisMessageBroker\MessageEnvelope(uniqid(), 'un message');

$producer = new RedisMessageBroker\MessageHandler\Producer($queue, $redisClient);
$producer->publishMessage($message);

```

### consumer

Consumer should be wrapped in a worker. A unique Id should be pass to the consumer constructor. If you work with a worker, uniqId has to be constant per worker.

```php
<?php
use M6Web\Component\RedisMessageBroker;
use Predis\Client as PredisClient;

$redisClient = new PredisClient(); // refer to PredisDocumentation
$queue = new RedisMessageBroker\Queue\Definition('raoul');
$consumer = new RedisMessageBroker\MessageHandler\Consumer($queue, $redisClient, uniqid());

$message = $consumer->getMessage();

```

### inspector

Inspector methods allow you to count the messages in ready or processing in a queue.

```php
<?php
use M6Web\Component\RedisMessageBroker;
use Predis\Client as PredisClient;

$redisClient = new PredisClient(); // refer to PredisDocumentation
$queue = new RedisMessageBroker\Queue\Definition('raoul');

$inspector = new RedisMessageBroker\Queue\Inspector($queue, $redisClient);
$countInProgress = $inspector->countInProgressMessages();
$countReady = $inspector->countReadyMessages();
```

### cleanup

Cleanup methods let you perform a cleanup in the message queue. Cleanup is very slow as all the message in the queue will be scanned.

```php
<?php
use M6Web\Component\RedisMessageBroker;
use Predis\Client as PredisClient;

$redisClient = new PredisClient(); // refer to PredisDocumentation
$queue = new RedisMessageBroker\Queue\Definition('raoul');

$cleanup = new RedisMessageBroker\Queue\Cleanup($queue, $redisClient);
$cleanup->cleanOldMessages(
    3600, // erase messages older than 3600 seconds
    true  // clean message in the ready queue too. Mandatory use if you are in no-autoack mode 
);
```

## queue option

To avoid hotpsots (when using redis in a cluster) you can shard a queue on several lists : 

```php
$queue = new RedisMessageBroker\Queue\Definition('raoul', 10); // shard on 10 lists
```

In this mode, messages will be written and read among the 10 lists. FIFO is no more guaranteed.

## consumer options 

### manual message acknowledgment

with `setNoAutoAck()`


```php
<?php
use M6Web\Component\RedisMessageBroker;
use Predis\Client as PredisClient;

$redisClient = new PredisClient(); // refer to PredisDocumentation
$queue = new RedisMessageBroker\Queue\Definition('raoul');
$consumer = new RedisMessageBroker\MessageHandler\Consumer($queue, $redisClient, uniqid());
$consumer->setNoAutoAck();

$message = $consumer->getMessage();
if ($message) {
    // do something with the message
    $consumer->ack($message); // erase the message from the working list
}
```

### look for old messages not acked by consumers

Each consumer got an unique Id defined during the construction of the object. This Id allow the consumer to define a unique working list where a message is stored between the `getMessage` and the `ack`.
Is it possible to use LostMessageConsumer class to look on consumer working lists and move message more than x second old (`messageTtl` parameter) from those lists to a queue list.
The `maxRetry` parameter will put a message in a dead letter list when the retry number is reached.
 
 ```php
 <?php
 use M6Web\Component\RedisMessageBroker;
 use Predis\Client as PredisClient;
 
 $redisClient = new PredisClient(); // refer to PredisDocumentation
 $queue = new RedisMessageBroker\Queue\Definition('raoul');
 $consumer = new RedisMessageBroker\MessageHandler\LostMessagesConsumer($queue, $redisClient, 360, 3);
 $consumer->checkWorkingLists();
 ```

 `messageTtl` parameter need to be superior of your max time to process a message. (Otherwise it will consider a message old when its processing)
