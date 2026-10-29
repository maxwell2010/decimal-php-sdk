<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$socket=new Decimal\WebSocket(getenv('PHP_TEST_WS')?:throw new RuntimeException('Loopback runner required'));
$id=$socket->subscribe('newHeads');
for($i=0;$i<2;++$i){$msg=$socket->receive();if(($msg['params']['result']['number']??null)!=='0x'.$i)throw new RuntimeException('Notification lost or reordered');}
$mapping=$socket->reconnect();if(($mapping[$id]??null)!=='0xsub')throw new RuntimeException('Missing resubscription mapping');
for($i=0;$i<2;++$i){$msg=$socket->receive();if(($msg['params']['result']['number']??null)!=='0x'.$i)throw new RuntimeException('Reconnected notification lost');}
if(!$socket->unsubscribe($mapping[$id]))throw new RuntimeException('Reconnected unsubscribe failed');$socket->close();
