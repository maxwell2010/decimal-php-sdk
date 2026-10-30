<?php
declare(strict_types=1);
$nh=json_decode(file_get_contents(dirname(__DIR__).'/docs/live/nft-position-search.json'),true,512,JSON_THROW_ON_ERROR);
$nhChecks=array_column($nh['checks'],null,'name');
$nhSnapshot=$nhChecks['testnet.snapshot']['value'];
$nhPosition=$nhChecks['testnet.event_block_position.1']['value'];
$nhBlock=null;
foreach($nh['requests']as $record)if($record['chain_id']===202020&&$record['method']==='eth_call'&&($record['params'][0]['to']??null)===$nhSnapshot['deployment']&&($record['params'][1]??null)!==$nhSnapshot['block']){$nhBlock=$record['params'][1];break;}
if($nhBlock===null)throw new RuntimeException('Historical event block fixture required');
$nhRpc=new Decimal\Rpc('http://unused',function($r)use($nh){
 if($r['method']==='eth_chainId')return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>'0x31524'];
 foreach($nh['requests']as $row)if($row['chain_id']===202020&&$row['method']===$r['method']&&$row['params']===$r['params'])return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$row['result']];
 throw new RuntimeException('Unexpected historical NFT request or unpinned block');
});
$nhClient=new Decimal\Client(Decimal\Network::testnet(),$nhRpc);
$nhReader=new Decimal\NftStakingReader($nhClient,$nhSnapshot['deployment']);
$nhRead=fn($b)=>$nhReader->stake($nhPosition['nftContract'],$nhPosition['tokenId'],$nhPosition['delegator'],$nhPosition['validator'],'0',$b);
same($nhRead($nhBlock),$nhPosition);same($nhPosition['isActive'],true);
same($nhRead($nhSnapshot['block'])['isActive'],false);
$nhOps=new Decimal\NftOperations($nhClient,$nhPosition['nftContract'],721);
$nhDraft=$nhOps->withdraw($nhPosition['delegator'],$nhSnapshot['deployment'],$nhPosition['validator'],$nhPosition['tokenId'],'1',null,'Delegation20261030');
same($nhClient->simulate($nhDraft,$nhBlock),'0x');
echo "Actual historical funded unified NFT position and successful withdrawal eth_call replayed offline\n";
