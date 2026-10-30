<?php
declare(strict_types=1);
require __DIR__.'/live_rpc_common.php';
$audit=new LiveRpcAudit('Actual incompatible candidate rejected by verified Bridge binding',60);
$client=$audit->client(Decimal\Network::testnet());
$audit->check('verified_factory_rejects_recorded_profile',function()use($client,$audit){
    try{
        // Observed fingerprints are used for a negative compatibility test, not production provenance.
        Decimal\BridgeService::verified($client,'0x827ea5381adb912ed0befda26afe8cdc5a5e050a','0xb199d4e593e9c2c68b5500d4a59173e0f583d6223ae9b968998fd5e1faac4b78','2','0x06d7ac3874adc1f773c5165e91077c8037b789ff','0xf4304c13b5611dafda19a48666c12e43a9a4333c83d978eacbd32785fd5e4383');
        throw new RuntimeException('Candidate unexpectedly accepted the selected profile; re-review the deployment');
    }catch(Decimal\RpcException $e){
        if($e->getCode()!==3)throw $e;
        $last=end($audit->requests);
        if($last['method']!=='eth_call'||!str_starts_with($last['params'][0]['data'],'0x5cd9d57e'))throw new RuntimeException('Unexpected rejection stage');
        return ['rejected_at'=>'minimalServiceFees(uint16)','code'=>$e->getCode(),'data'=>$e->rpcData,'approval_plan_created'=>false];
    }
});
$audit->save(dirname(__DIR__).'/docs/live/bridge-binding.json');
echo json_encode(['requests'=>count($audit->requests),'checks'=>$audit->checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
exit($audit->exitCode());
