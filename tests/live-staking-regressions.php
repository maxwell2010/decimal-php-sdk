<?php
declare(strict_types=1);
// Recorded real mainnet/testnet responses. This regression file never contacts a provider.
$ls=json_decode(file_get_contents(__DIR__.'/live-staking-vectors.json'),true,512,JSON_THROW_ON_ERROR);
same(count($ls['rows']),4);
foreach($ls['rows'] as $row){
 $contract=Decimal\Contract::profile($row['address'],'Delegation20261030');$decoded=$contract->decode($row['signature'],$row['data'])[0];
 if($row['signature']==='getStakes(bytes32[])'){
  same(count($decoded),4);foreach($decoded as $stake)same(count($stake),8);
  same(count(array_filter($decoded,fn($stake)=>$stake[3]!=='0')),$row['chain_id']===75?4:0);
 }else{same(count($decoded),10);same($decoded[9],false);}
 rejects(fn()=>$contract->decode($row['signature'],$row['data'].str_repeat('00',32)));
}
$lsAbi=json_decode(file_get_contents(dirname(__DIR__).'/abi/Delegation20261030.json'),true);$lsCounts=[];
foreach($ls['logs'] as $log){$event=Decimal\Events::decode($lsAbi,$log,'0xa16c34ed1c0601c0e749e17ebef19752a15faa01');$lsCounts[$event['event']]=($lsCounts[$event['event']]??0)+1;if($event['event']==='StakeUpdated')same(count($event['args']['stake']),8);}
same($lsCounts,['StakeAmountUpdated'=>8,'StakeUpdated'=>8]);
echo "Recorded live mainnet/testnet stakes and real upgraded events passed offline\n";
