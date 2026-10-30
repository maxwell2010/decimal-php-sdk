<?php
declare(strict_types=1);
// Replay exact public RPC responses captured with verified TLS. No network transport exists here.
$ltr=json_decode(file_get_contents(__DIR__.'/live-token-vectors.json'),true,512,JSON_THROW_ON_ERROR);$ltrTables=[];$ltrCenters=[];
foreach($ltr['requests'] as $r)$ltrTables[$r['chain_id']][$r['method'].':'.json_encode($r['params'])]=$r['result'];
foreach($ltr['checks'] as $row)if(str_ends_with($row['name'],'.registry.token-center'))$ltrCenters[explode('.',$row['name'])[0]]=$row['value'];
foreach($ltr['checks'] as $row){
 if(!str_ends_with($row['name'],'.properties'))continue;
 [$label,$kind]=explode('.',$row['name']);$expected=$row['value'];$chain=$label==='mainnet'?75:202020;$table=$ltrTables[$chain];$used=0;
 $rpc=new Decimal\Rpc('http://unused',function($r)use($table,&$used){++$used;$key=$r['method'].':'.json_encode($r['params']);if(!array_key_exists($key,$table))throw new RuntimeException('Unexpected replay RPC: '.$key);return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$table[$key]];});
 $network=$label==='mainnet'?Decimal\Network::mainnet():Decimal\Network::testnet();$client=new Decimal\Client($network,$rpc);$economy=new Decimal\CurrentTokenEconomy($client,$ltrCenters[$label]);
 same($economy->properties($expected['token']['address'],$kind==='reserveless',$expected['block']),$expected);same($used>0,true);
 if($kind==='reserve'){
  $reader=new Decimal\TokenReader($client,$expected['token']['address']);
  foreach($ltr['checks'] as $quote)if(str_starts_with($quote['name'],$label.'.reserve.calculate'))same($reader->quote(substr($quote['name'],strlen($label.'.reserve.')),'1000000000000000000',null,$expected['block']),$quote['value']);
 }
}
echo "Captured mainnet/testnet token state and quote replay regressions passed (offline only)\n";
