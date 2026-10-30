<?php
declare(strict_types=1);
$upgradeOwner='0x'.str_repeat('11',20);$upgradeNft='0x'.str_repeat('22',20);$upgradeDeployment='0x'.str_repeat('33',20);$upgradeValidator='0x'.str_repeat('44',20);
$upgradeCodec=new Decimal\AbiCodec();$upgradeRequests=[];
$upgradeRpc=new Decimal\Rpc('http://unused',function($r)use(&$upgradeRequests,$upgradeOwner,$upgradeCodec){
    $upgradeRequests[]=$r;
    $result='0x31524';
    if($r['method']==='eth_call')$result=match(substr($r['params'][0]['data'],0,10)) {
        '0x6352211e'=>$upgradeCodec->encodeParameters(['address'],[$upgradeOwner]),
        '0x081812fc'=>$upgradeCodec->encodeParameters(['address'],['0x'.str_repeat('00',20)]),
        '0xe985e9c5'=>$upgradeCodec->encodeParameters(['bool'],[false]),
        '0x00fdd58e'=>$upgradeCodec->encodeParameters(['uint256'],['9']),
        default=>throw new RuntimeException('Unexpected request'),
    };
    return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];
});
$upgradeService=new Decimal\NftService(new Decimal\Client(Decimal\Network::testnet(),$upgradeRpc),$upgradeNft);
foreach([false,true] as $upgrade1155) {
    $plan=$upgrade1155?$upgradeService->delegate1155($upgradeOwner,$upgradeDeployment,$upgradeValidator,'7','3','0x10','Delegation20261030'):$upgradeService->delegate721($upgradeOwner,$upgradeDeployment,$upgradeValidator,'7','0x10','Delegation20261030');
    same(count($plan['approvals']),1);same($plan['approvals'][0]->to,$upgradeNft);same($plan['action']->to,$upgradeDeployment);
    same($plan['action']->data,Decimal\Contract::profile($upgradeDeployment,'Delegation20261030')->encode($upgrade1155?'delegateNFT1155(address,address,uint256,uint256)':'delegateNFT(address,address,uint256)',$upgrade1155?[$upgradeValidator,$upgradeNft,'7','3']:[$upgradeValidator,$upgradeNft,'7']));
}
$upgradeMainnet=new Decimal\NftService(new Decimal\Client(Decimal\Network::mainnet(),$upgradeRpc),$upgradeNft);$before=count($upgradeRequests);
rejects(fn()=>$upgradeMainnet->delegate721($upgradeOwner,$upgradeDeployment,$upgradeValidator,'7'));
rejects(fn()=>$upgradeMainnet->delegate1155($upgradeOwner,$upgradeDeployment,$upgradeValidator,'7','3'));
rejects(fn()=>$upgradeService->delegate721($upgradeOwner,$upgradeDeployment,$upgradeValidator,'7','latest','Token'));
same(count($upgradeRequests),$before);
$upgradeAbi=json_decode(file_get_contents(dirname(__DIR__).'/abi/Delegation20261030.json'),true);
$upgradeLegacy=json_decode(file_get_contents(dirname(__DIR__).'/abi/Delegation.json'),true);
foreach([$upgradeAbi,$upgradeLegacy] as $sourceAbi) {
    $event=array_values(array_filter($sourceAbi,fn($e)=>($e['type']??'')==='event'&&$e['name']==='StakeUpdated'))[0];
    $stake=[$upgradeValidator,$upgradeOwner,$upgradeNft,'1000000000000000001','7','2','2000'];
    $tuple=array_values(array_filter($event['inputs'],fn($i)=>$i['type']==='tuple'))[0];if(count($tuple['components'])===8)$stake[]='1234';
    $values=['0x'.str_repeat('ab',32),$stake,true];
    $log=['address'=>$upgradeDeployment,'topics'=>['0x'.kornrunner\Keccak::hash(Decimal\Contract::signature($event),256)],'data'=>$upgradeCodec->encodeParameters(array_map(Decimal\Contract::type(...),$event['inputs']),$values)];
    foreach(['Delegation','Delegation20261030'] as $profile) {
        $decoded=Decimal\Events::receipt($profile,['status'=>'0x1','logs'=>[$log]],$upgradeDeployment);
        same(count($decoded),1);same($decoded[0]['signature'],Decimal\Contract::signature($event));same(array_values($decoded[0]['args']),$values);
    }
    $bad=$log;$bad['data'].=str_repeat('00',32);rejects(fn()=>Decimal\Events::decode($upgradeAbi,$bad,$upgradeDeployment));
    $bad=$log;$bad['address']=$upgradeOwner;rejects(fn()=>Decimal\Events::decode($upgradeAbi,$bad,$upgradeDeployment));
}
