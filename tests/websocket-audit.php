<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$base=getenv('PHP_TEST_WS')?:throw new RuntimeException('Loopback runner required');
foreach(['future','both','nullerror','badnotification','oversizequeue','pings'] as $case){
 $socket=new Decimal\WebSocket($base.'/'.$case,$case==='pings'?1:15);
 $rejected=false;try{$socket->subscribe('newHeads');}
 catch(RuntimeException $expected){if($case!=='pings'&&!$expected instanceof Decimal\RpcException)throw $expected;$rejected=true;}
 finally{$socket->close();}
 if(!$rejected)throw new RuntimeException('Invalid peer response accepted: '.$case);
}
$socket=new Decimal\WebSocket($base.'/options');
try{$socket->subscribe('logs',['address'=>'0x'.str_repeat('12',20)]);throw new RuntimeException('Non-positional options accepted');}
catch(InvalidArgumentException $expected){}
finally{$socket->close();}
echo "WebSocket poisoning, envelope and queue-bound checks passed\n";
