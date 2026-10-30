<?php
declare(strict_types=1);
$srA='0x'.str_repeat('11',20);$srT='0x'.str_repeat('22',20);$srD='0x'.str_repeat('44',20);$srCodec=new Decimal\AbiCodec();$srSeen=[];
$srRow=[$srA,$srA,$srT,'1250000','0','0','123','100'];$srTuple='(address,address,address,uint256,uint256,uint8,uint256,uint256)';
$srRpc=new Decimal\Rpc('http://unused',function($r)use(&$srSeen,$srCodec,$srRow,$srTuple){
    $srSeen[]=$r;
    if($r['method']==='eth_chainId')$value='0x31524';elseif($r['method']==='eth_blockNumber')$value='0x10';
    elseif($r['method']==='eth_call'){$selector=substr($r['params'][0]['data'],0,10);$value=match($selector){
        '0x'.substr(kornrunner\Keccak::hash('getValidator(address)',256),0,8)=>$srCodec->encodeParameters(['(uint8,bool,uint16[])'],[['1',true,['12','34']]]),
        '0x'.substr(kornrunner\Keccak::hash('getRewardPerBlock()',256),0,8)=>$srCodec->encodeParameters(['uint256','bool'],['100',true]),
        default=>$srCodec->encodeParameters([$srTuple],[$srRow])};}
    else throw new RuntimeException('Unexpected remote write');
    return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$value];
});
$srClient=new Decimal\Client(Decimal\Network::testnet(),$srRpc);$srReader=new Decimal\StakingReader($srClient,Decimal\Contract::profile($srD,'Delegation'));
$snapshot=$srReader->snapshot($srA,$srA,$srT,['123']);same($snapshot['block'],'0x10');same($snapshot['total_amount_raw'],'2500000');same($snapshot['regular']->amount(6),'1.25');same($snapshot['holds'][0]->matured('123'),true);same($snapshot['holds'][0]->fields['holdStartTime'],'100');
foreach($srSeen as $r)if($r['method']==='eth_call')same($r['params'][1],'0x10');
$srValidator=new Decimal\ValidatorService($srClient,$srD);same($srValidator->read('validator',$srA),['status'=>'1','paused'=>true,'penaltyPercentages'=>['12','34']]);same($srValidator->read('reward'),['rewardPerBlock'=>'100','enabled'=>true]);
$draft=$srValidator->draft('add_validator_del',$srA,['owner'=>$srA,'validator'=>$srT,'meta'=>'meta','amount_del'=>'1.25']);same($draft->value,'1250000000000000000');
rejects(fn()=>$srValidator->draft('add_validator_del',$srA,['owner'=>$srT]));rejects(fn()=>$srValidator->draft('add_candidate_token',$srA,['validator'=>$srT,'meta'=>'meta','token'=>$srT,'amount_raw'=>'0']));rejects(fn()=>$srValidator->draft('update_meta',$srA,['validator'=>$srT,'meta'=>' ']));
$abi=json_decode(file_get_contents(dirname(__DIR__).'/abi/Delegation20261030.json'),true);$event=array_values(array_filter($abi,fn($e)=>($e['type']??'')==='event'&&$e['name']==='WithdrawRequest'))[0];
$hash='0x'.str_repeat('ab',32);$log=['address'=>$srD,'topics'=>['0x'.kornrunner\Keccak::hash(Decimal\Contract::signature($event),256)],'data'=>$srCodec->encodeParameters(array_map(Decimal\Contract::type(...),$event['inputs']),[$hash,'1',[$srRow,'1','1','456']]),'transactionHash'=>$hash];$receipt=['status'=>'0x1','transactionHash'=>$hash,'blockNumber'=>'0x10','logs'=>[$log]];
$withdrawals=Decimal\StakingReader::withdrawalReceipt($receipt,$srD);same(count($withdrawals),1);same($withdrawals[0]['stakeId'],$hash);same($withdrawals[0]['frozenStake']->stake['holdStartTime'],'100');
$bad=$receipt;$bad['logs'][0]['removed']=true;rejects(fn()=>Decimal\StakingReader::withdrawalReceipt($bad,$srD));$bad=$receipt;$bad['logs'][0]['transactionHash']='0x'.str_repeat('cd',32);rejects(fn()=>Decimal\StakingReader::withdrawalReceipt($bad,$srD));
echo "Staking snapshots, withdrawal receipt and validator parity passed\n";

rejects(fn()=>$srValidator->draft('add_validator_del',$srA,[]));$srBadType=$srRow;$srBadType[5]='256';rejects(fn()=>new Decimal\StakePosition($srBadType));
