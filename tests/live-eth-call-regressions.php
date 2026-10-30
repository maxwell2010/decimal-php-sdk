<?php
declare(strict_types=1);
$liveCalls=json_decode(file_get_contents(dirname(__DIR__).'/docs/live/eth-call.json'),true,512,JSON_THROW_ON_ERROR);
$livePositive=0;$liveNegative=0;
foreach($liveCalls['requests'] as $row){
    if($row['method']!=='eth_call')continue;
    $data=$row['params'][0]['data'];if(!in_array(substr($data,0,10),['0x095ea7b3','0xa9059cbb'],true))continue;
    $signature=str_starts_with($data,'0x095ea7b3')?'approve(address,uint256)':'transfer(address,uint256)';
    if(isset($row['result'])){same(Decimal\Contract::profile($row['params'][0]['to'],'Token')->decode($signature,$row['result']),[true]);same($row['params'][0]['value'],'0x0');++$livePositive;}
    else{
        $error=$row['error'];$replay=new Decimal\Rpc('http://unused',fn($r)=>['jsonrpc'=>'2.0','id'=>$r['id'],'error'=>['code'=>$error['code'],'message'=>$error['message'],'data'=>$error['data']]]);
        try{$replay->call('eth_call',$row['params']);throw new RuntimeException('Expected captured node revert');}catch(Decimal\RpcException $e){same($e->getCode(),3);same($e->rpcData,'0x');}++$liveNegative;
    }
}
same($livePositive,4);same($liveNegative,2);
echo "Captured nonpersistent ERC20 eth_call successes and node reverts replayed offline\n";
