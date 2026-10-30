<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Decimal\{BridgeService,ChecksService,Client,Erc20Service,Network,Rpc,TransactionPlan};

// Placeholder addresses and an RPC that rejects every call: this example is entirely offline.
$sender='0x'.str_repeat('11',20);$recipient='0x'.str_repeat('22',20);$deployment='0x'.str_repeat('33',20);
$client=new Client(Network::testnet(),new Rpc('http://unused',fn()=>throw new RuntimeException('Unexpected RPC call')));
$transfer=(new Erc20Service($client,$deployment))->transferRaw($sender,$recipient,'1000000000000000000');
$bridge=(new BridgeService($client,$deployment))->transferNative($sender,$recipient,'1000000000000000000','1000','75','42');
$checks=(new ChecksService($client,$deployment))->createDelForSigners($sender,[$recipient],'100','50000000','0');
foreach(['erc20'=>$transfer,'bridge'=>$bridge,'checks'=>$checks] as $name=>$draft){
    $plan=TransactionPlan::from($draft);
    echo $name,': ',json_encode($plan->action->rpc(),JSON_THROW_ON_ERROR),PHP_EOL;
}

// Current token profile factory and testnet creation encoding also stay completely offline.
$sdk=new Decimal\Sdk($client);
$created=$sdk->tokenEconomy($deployment)->create($sender,'Example Token','example','1000000000000000000','0','10000000000000000000',50,'offline');
echo 'current-token-create: ',json_encode($created->rpc(),JSON_THROW_ON_ERROR),PHP_EOL;
