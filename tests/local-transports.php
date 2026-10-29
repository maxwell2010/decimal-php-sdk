<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Decimal\{Api,Client,Network,WebSocket};
$http=getenv('PHP_TEST_HTTP')?:throw new RuntimeException('Loopback test runner required');
$ws=getenv('PHP_TEST_WS')?:throw new RuntimeException('Loopback test runner required');
$client=new Client(new Network(202020,$http,'0x'.str_repeat('12',20)));
if($client->balance('0x'.str_repeat('12',20))!=='123')throw new RuntimeException('HTTP RPC mismatch');
if((new Api($http))->get('validators',['limit'=>2])!==['path'=>'/validators?limit=2'])throw new RuntimeException('HTTP API mismatch');
$socket=new WebSocket($ws);$id=$socket->subscribe('newHeads');
if($id!=='0xsub')throw new RuntimeException('Subscription mismatch');
$notification=$socket->receive();
if(($notification['params']['result']['number']??null)!=='0x1')throw new RuntimeException('Notification mismatch');
if(!$socket->unsubscribe($id))throw new RuntimeException('Unsubscribe failed');
$socket->close();
