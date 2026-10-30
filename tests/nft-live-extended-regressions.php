<?php
declare(strict_types=1);
$extended=json_decode(file_get_contents(dirname(__DIR__).'/docs/live/nft-extended.json'),true,512,JSON_THROW_ON_ERROR);$mintResults=[];$voidCount=0;
foreach($extended['checks'] as $check){
 if($check['status']!=='passed'||!str_contains($check['name'],'.eth_call.'))continue;
 $v=$check['value'];$draft=new Decimal\TransactionDraft($v['draft']['from'],$v['draft']['to'],Decimal\Units::decimal($v['draft']['value']),$v['draft']['data']);same($draft->rpc(),$v['draft']);
 if(str_ends_with($check['name'],'.mint')){$mintResults[]=$v['result'];same($v['scope'],'ephemeral EVM execution only; no token was created on chain');}else{same($v['result'],'0x');++$voidCount;}
}
same(count($mintResults),3);same($voidCount,9);same((new Decimal\AbiCodec())->decodeParameters(['uint256'],$mintResults[0]),['1']);
foreach($extended['requests'] as $request)same(in_array($request['method'],['eth_chainId','eth_blockNumber','eth_call'],true),true);
echo "Actual NFT action eth_call records replayed without signing or network access\n";
