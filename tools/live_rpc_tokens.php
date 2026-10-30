<?php
declare(strict_types=1);
require_once __DIR__.'/live_rpc_common.php';
$audit=new LiveRpcAudit('tokens',250);
foreach(['mainnet'=>Decimal\Network::mainnet(),'testnet'=>Decimal\Network::testnet()] as $label=>$network){
 $client=$audit->client($network);$block=$audit->check($label.'.block',function()use($client){$client->verifyChain();return $client->rpc->call('eth_blockNumber');});
 if($block===null){$audit->note($label.'.coverage','limitation','Primary endpoint unavailable; no contract results inferred');continue;}
 $addresses=[];foreach(['token-center','wdel'] as $slug)$addresses[$slug]=$audit->check($label.'.registry.'.$slug,fn()=>$client->resolveSystemContract($slug,$block));
 if($addresses['wdel'])$audit->check($label.'.wdel.metadata',fn()=>(new Decimal\Erc20Service($client,$addresses['wdel']))->info($block));
 $tokens=$label==='testnet'?['reserve'=>'0x37748f3e857A71E4334125A935052Ce1e603b7B5','reserveless'=>'0xC310F25E3429eFc5EdE11A908682F0a31cfc92Ce']:['reserve'=>'0x60cf963b60c8f61a45e1a1c2b6b50ba582c39880'];
 foreach($tokens as $kind=>$token){
  $code=$audit->check($label.'.'.$kind.'.code',fn()=>$client->rpc->call('eth_getCode',[$token,$block]));
  if(!$code||$code==='0x'){$audit->note($label.'.'.$kind.'.coverage','limitation','Previously observed token has no code at current block');continue;}
  $audit->check($label.'.'.$kind.'.metadata',fn()=>(new Decimal\Erc20Service($client,$token))->info($block));
  $reader=new Decimal\TokenReader($client,$token,$kind==='reserveless');
  foreach($kind==='reserveless'?['cap','mintable','burnable','owner','identity']:['creator','identity','reserve','crr','minTotalSupply','maxTotalSupply','getCurrentPrice'] as $field)$audit->check($label.'.'.$kind.'.'.$field,fn()=>$reader->read($field,$block));
  if($kind==='reserve')foreach(['calculateBuyInput','calculateBuyOutput','calculateSellInput','calculateSellOutput'] as $quote)$audit->check($label.'.reserve.'.$quote,fn()=>$reader->quote($quote,'1000000000000000000',null,$block));
  if($addresses['token-center'])$audit->check($label.'.'.$kind.'.properties',fn()=>(new Decimal\CurrentTokenEconomy($client,$addresses['token-center']))->properties($token,$kind==='reserveless',$block));
 }
}
$audit->save(dirname(__DIR__).'/docs/live/tokens.json');
echo json_encode(['requests'=>count($audit->requests),'checks'=>$audit->checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;

exit($audit->exitCode());
