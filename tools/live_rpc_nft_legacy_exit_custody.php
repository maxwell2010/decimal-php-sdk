<?php
declare(strict_types=1);
require __DIR__.'/live_rpc_common.php';
// A second pinned diagnosis, independent of current state. No state overrides.
$audit=new LiveRpcAudit('Legacy NFT exit custody and beacon diagnosis',25);
$client=$audit->client(Decimal\Network::mainnet());
$block='0x20c281d';
$asset='0xd5224d2e0f46171eb24a7134ab2accccd2303f9b';
$legacy='0xe45adfcc739a0d10ce9462b58866c9a1a06035e2';
$delegator='0x68e4f512b4e3bf368e910908541994ea3c7e9fc9';
// Beacon address is embedded in the recorded asset proxy runtime, not guessed storage.
$beacon='0xd2f60b34f7a70229c1556d4e0705f9b8f7b107b3';
$audit->note('snapshot','observed',compact('block','asset','legacy','delegator','beacon'));
$audit->check('beacon.runtime',fn()=>$client->rpc->call('eth_getCode',[$beacon,$block]));
$contract=new Decimal\Contract($beacon,[['type'=>'function','name'=>'implementation','inputs'=>[],'outputs'=>[['name'=>'','type'=>'address']]]]);
$implementation=$audit->check('beacon.implementation',fn()=>$contract->call($client,'implementation()',[],$block)[0]);
if($implementation!==null)$audit->check('implementation.runtime',fn()=>$client->rpc->call('eth_getCode',[$implementation,$block]));
$pause=new Decimal\Contract($legacy,[['type'=>'function','name'=>'paused','inputs'=>[],'outputs'=>[['name'=>'','type'=>'bool']]]]);
$audit->check('legacy.paused',fn()=>$pause->call($client,'paused()',[],$block)[0]);
$reader=new Decimal\NftReader($client,$asset,721);
$audit->check('asset.ownerOf1',fn()=>$reader->ownerOf('1',$block));
$operations=new Decimal\NftOperations($client,$asset,721);
$audit->check('asset.custodyTransfer',fn()=>$client->call($operations->transferFrom($legacy,$legacy,$delegator,'1')->rpc(),$block));
$staking=new Decimal\NftLegacyStaking($client,$legacy);
$audit->check('legacy.complete0',fn()=>$client->call($staking->plan('complete_stake',$delegator,['indexes'=>['0']])->rpc(),$block));
foreach($audit->checks as &$entry)if($entry['status']==='failed'&&($entry['error']['code']??null)===3)$entry['status']='node-revert-observed';unset($entry);
$audit->note('interpretation','unresolved','The completion error selector matches EnforcedPause(); direct owner/transfer errors match ERC721NonexistentToken(1). Selector matching alone does not authenticate deployed source. A direct custody simulation does not prove the nested completion path.');
$audit->save(dirname(__DIR__).'/docs/live/nft-legacy-exit-custody.json');
echo json_encode(['requests'=>count($audit->requests),'checks'=>$audit->checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
exit($audit->exitCode());

