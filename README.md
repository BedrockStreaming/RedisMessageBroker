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
$message = new RedisMessageBroker\Message('un message');

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
$consumer = new RedisMessageBroker\MessageHandler\Consumer($squeue, $redisClient, uniqid());

$message = $consumer->getMessage();

```

### inspector

Inspector methods allow you to count the messages in ready or processing in a queue.

```php
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

### look for old messages not acked by other consumers

Each consumer got an unique Id defined during the construction of the object. This Id allow the consumer to define a unique working list where a message is stored between the `getMessage` and the `ack`.
Is it possible to tell a consumer to look on other consumer working lists and get a message from those lists with the `setTimeOldMessage` method. 
 
 ```php
 $consumer->setNoAutoAck();
 $consumer->setTimeOldMessage(360);
 ```
 
Doing this, the consumer will first look in non acked message in his own working list. Then it will check in all the working list of the other workers (concerned by the queue) if there is a message more than 360s old. If so the message is returned and be acked normally (this can be slow). Finally, he will look on his ready message list.

