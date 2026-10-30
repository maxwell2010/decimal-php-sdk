<?php
declare(strict_types=1);
$liveNftRows=json_decode(file_get_contents(__DIR__.'/live-nft-vectors.json'),true,512,JSON_THROW_ON_ERROR);
same(count($liveNftRows),1);$liveNftRow=$liveNftRows[0];$liveNftInputs=[];
$liveNftRpc=new Decimal\Rpc('http://unused',function($r)use($liveNftRow,&$liveNftInputs){$liveNftInputs[]=$r;return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$r['method']==='eth_chainId'?'0x4b':$liveNftRow['result']];});
$liveNftCurrent=new Decimal\NftCurrentOperations(new Decimal\Client(Decimal\Network::mainnet(),$liveNftRpc),'0x9b1129ec66afd4b7d20f95de078126853695ccfe',721,$liveNftRow['params'][0]['to']);
same($liveNftCurrent->collectionState($liveNftRow['params'][1]),['active'=>true,'standard'=>721,'withReserve'=>false]);
same($liveNftInputs[1]['params'],$liveNftRow['params']);
$unsupportedNftRpc=new Decimal\Rpc('http://unused',fn($r)=>$r['method']==='eth_chainId'?['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>'0x4b']:['jsonrpc'=>'2.0','id'=>$r['id'],'error'=>['code'=>3,'message'=>'execution reverted','data'=>'0x']]);
$unsupportedNftReader=new Decimal\NftReader(new Decimal\Client(Decimal\Network::mainnet(),$unsupportedNftRpc),'0x9b1129ec66afd4b7d20f95de078126853695ccfe',721);
rejects(fn()=>$unsupportedNftReader->refundable());rejects(fn()=>$unsupportedNftReader->nftType());
echo "Recorded mainnet NFT state and unsupported getter propagation verified offline\n";
