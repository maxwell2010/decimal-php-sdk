<?php
declare(strict_types=1);
use Decimal\{Rpc,RpcException,RpcTransportException,Api,Indexer};
$transportAddress='0x'.str_repeat('12',20);
$transportCalls=[];
$transportRpc=new Rpc(['https://first.test','https://second.test'],function($r,$url)use(&$transportCalls){$transportCalls[]=[$r['method'],$url];if($url==='https://first.test')throw new RpcTransportException('offline');return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$r['method']==='eth_chainId'?'0x31524':'0x7b'];},202020);
same($transportRpc->call('eth_getBalance',[$transportAddress,'latest']),'0x7b');same(count($transportCalls),3);
$transportCalls=[];rejects(fn()=>$transportRpc->call('eth_sendRawTransaction',['0xdead']));same(count($transportCalls),1);
$transportWrong=new Rpc(['https://first.test','https://second.test'],fn($r,$url)=>['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>'0x4b'],202020);rejects(fn()=>$transportWrong->call('eth_getBalance',[$transportAddress,'latest']));
rejects(fn()=>new Rpc(['https://a.test','https://b.test']));rejects(fn()=>new Rpc('file:///secret'));rejects(fn()=>new Rpc('https://user:pass@example.test'));
$transportAttempts=0;$transportRevert=new Rpc(['https://a.test','https://b.test'],function($r)use(&$transportAttempts){++$transportAttempts;return $r['method']==='eth_chainId'?['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>'0x31524']:['jsonrpc'=>'2.0','id'=>$r['id'],'error'=>['code'=>-32000,'message'=>'revert','data'=>'0xdead']];},202020);
rejects(fn()=>$transportRevert->call('eth_call',[]));same($transportAttempts,2);
$transportBad=new Rpc('https://a.test',fn($r)=>['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>true,'error'=>['code'=>-1,'message'=>'bad']]);rejects(fn()=>$transportBad->call('eth_chainId'));
$transportApi=new Api('https://api.test/api',fn($u)=>$u);
foreach(['%252e%252e/secret','x%00y','x%0ay','%2f%2fhost.test','a/%2e%2e/b','a/%2e%2e%3b/secret'] as $bad)rejects(fn()=>$transportApi->get($bad));
$transportIndexer=new Indexer($transportApi);
same($transportIndexer->latestBlock(),'https://api.test/api/blocks/latest');same($transportIndexer->walletTransfers($transportAddress),'https://api.test/api/validators/wallet/'.$transportAddress.'/transfer/coins');same($transportIndexer->rewards($transportAddress),'https://api.test/api/rewards/delegators/'.$transportAddress);
same($transportIndexer->walletBalances($transportAddress,2),'https://api.test/api/erc20/balances/'.$transportAddress.'?limit=2&offset=0&include_bank=1&prefer_bank=1');
rejects(fn()=>$transportIndexer->coin('../secret'));rejects(fn()=>$transportIndexer->block(-1));
$transportIndex=['withdrawals_authoritative'=>true,'withdrawals_fresh'=>true,'withdrawals_checked_block'=>'123','withdrawals'=>[['contract_address'=>$transportAddress,'id'=>'7'],['contract_address'=>strtoupper(substr($transportAddress,2)),'id'=>'7']]];
$transportIndex['withdrawals'][1]['contract_address']=$transportAddress;
$transportReader=new Indexer(new Api('https://api.test',fn()=>['Result'=>$transportIndex]));same($transportReader->withdrawalIndex('index','123'),['block'=>'123','entries'=>[['contract'=>$transportAddress,'index'=>'7']]]);rejects(fn()=>$transportReader->withdrawalIndex('index','124'));
foreach(['withdrawals_authoritative','withdrawals_fresh'] as $flag){$bad=$transportIndex;$bad[$flag]=false;$reader=new Indexer(new Api('https://api.test',fn()=>['Result'=>$bad]));rejects(fn()=>$reader->withdrawalIndex('index'));}
echo "Transport release regression checks passed\n";
