<?php
declare(strict_types=1);
require __DIR__.'/live_rpc_common.php';
$audit=new LiveRpcAudit('Legacy mature NFT exit asset diagnosis',25);
$previous=json_decode(file_get_contents(dirname(__DIR__).'/docs/live/nft-positions.json'),true,512,JSON_THROW_ON_ERROR);$block=null;
foreach($previous['requests'] as $r)if($r['method']==='eth_call'&&isset($r['error'])&&str_starts_with($r['params'][0]['data']??'','0x5d95f94f'))$block=$r['params'][1];
if($block===null)foreach(array_reverse($previous['requests']) as $r)if($r['chain_id']===75&&$r['method']==='eth_call'){$block=$r['params'][1];break;}
$client=$audit->client(Decimal\Network::mainnet());$client->verifyChain();
$asset='0xd5224d2e0f46171eb24a7134ab2accccd2303f9b';$legacy='0xe45adfcc739a0d10ce9462b58866c9a1a06035e2';$delegator='0x68e4f512b4e3bf368e910908541994ea3c7e9fc9';
$reader=new Decimal\NftReader($client,$asset,721);
$audit->note('snapshot','observed',compact('block','asset','legacy','delegator'));
$audit->check('asset.runtime',fn()=>$client->rpc->call('eth_getCode',[$asset,$block]));
$audit->check('asset.ownerOf1',fn()=>$reader->ownerOf('1',$block));
$audit->check('asset.legacyBalance',fn()=>$reader->balanceOf($legacy,null,$block));
$audit->check('asset.delegatorBalance',fn()=>$reader->balanceOf($delegator,null,$block));
$audit->check('asset.creator',fn()=>$reader->creator($block));
$audit->check('asset.allowMint',fn()=>$reader->allowMint($block));
$audit->check('asset.refundable',fn()=>$reader->refundable($block));
$audit->check('asset.legacyNftType',fn()=>$reader->nftType(true,$block));
$audit->check('asset.reserve1',fn()=>(new Decimal\NftCurrentOperations($client,$asset,721,'0x443cc8ac24630be9257483a09c34ffa81608eace'))->reserve('1',$block));
$frozen=new Decimal\NftLegacyStaking($client,$legacy);$audit->check('legacy.frozen0',fn()=>$frozen->frozen('0',$block));
$erc165=new Decimal\Contract($asset,[['type'=>'function','name'=>'supportsInterface','stateMutability'=>'view','inputs'=>[['name'=>'interfaceId','type'=>'bytes4']],'outputs'=>[['name'=>'','type'=>'bool']]]]);
$audit->check('asset.supportsERC1155',fn()=>$erc165->call($client,'supportsInterface(bytes4)',['0xd9b67a26'],$block)[0]);
$audit->check('asset.legacyERC1155BalanceId1',fn()=>(new Decimal\NftReader($client,$asset,1155))->balanceOf($legacy,'1',$block));
foreach($audit->checks as &$entry)if($entry['status']==='failed'&&($entry['error']['code']??null)===3&&in_array($entry['name'],['asset.ownerOf1','asset.legacyNftType','asset.legacyERC1155BalanceId1'],true))$entry['status']='view-revert-observed';unset($entry);
$audit->note('completion_cause','unresolved-contract-state','ownerOf1 reverts and both ERC721 balances are zero while reserve/frozen metadata remain. This does not prove burn/migration or the exact completion revert cause.');
$audit->save(dirname(__DIR__).'/docs/live/nft-legacy-exit-asset.json');
echo json_encode(['requests'=>count($audit->requests),'checks'=>$audit->checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;

exit($audit->exitCode());
