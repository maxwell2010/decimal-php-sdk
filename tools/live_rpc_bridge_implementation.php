<?php
declare(strict_types=1);
require __DIR__.'/live_rpc_common.php';
$audit=new LiveRpcAudit('Current candidate implementation fingerprint',3);
$client=$audit->client(Decimal\Network::testnet());$client->verifyChain();
$block=null;$prior=json_decode(file_get_contents(dirname(__DIR__).'/docs/live/bridge-discovery.json'),true,512,JSON_THROW_ON_ERROR);
foreach($prior['checks'] as $check)if($check['name']==='testnet.snapshot')$block=$check['value'];
if(!is_string($block))throw new RuntimeException('Recorded candidate block required');
$address='0x06d7ac3874adc1f773c5165e91077c8037b789ff';
$audit->check('implementation.fingerprint',function()use($client,$address,$block){
    $code=$client->rpc->call('eth_getCode',[$address,$block]);
    if(!preg_match('/^0x(?:[0-9a-fA-F]{2})+$/D',$code))throw new RuntimeException('Missing implementation code');
    $marker='6970667358221220';$offset=strrpos(strtolower($code),$marker);
    return ['address'=>$address,'block'=>$block,'runtime_keccak'=>'0x'.kornrunner\Keccak::hash(hex2bin(substr($code,2)),256),'ipfs_sha256'=>$offset===false?null:substr($code,$offset+strlen($marker),64)];
});
$audit->save(dirname(__DIR__).'/docs/live/bridge-implementation.json');
echo json_encode($audit->checks,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
exit($audit->exitCode());
