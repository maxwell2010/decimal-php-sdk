<?php
declare(strict_types=1);
use Decimal\{Client,Network,Rpc,NonceManager,TransactionDraft};
$auditSender='0x'.str_repeat('12',20);$auditTo='0x'.str_repeat('34',20);
foreach([0,1] as $kind) {
    foreach([null,'7'] as $explicitPrice) {
        $requests=[];
        $auditRpc=new Rpc('http://unused',function($r)use(&$requests){
            $requests[]=$r;
            return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>match($r['method']){
                'eth_chainId'=>'0x31524','eth_gasPrice'=>'0x9','eth_call'=>'0x','eth_estimateGas'=>'0x5208','eth_getBalance'=>'0xde0b6b3a7640000','eth_getTransactionCount'=>'0x0',default=>throw new RuntimeException('Unexpected quote request')
            }];
        });
        $preparedAudit=(new Client(Network::testnet(),$auditRpc))->prepare(TransactionDraft::del($auditSender,$auditTo,'0.01'),type:$kind,maxFeePerGas:$explicitPrice);
        same($preparedAudit->gasPrice,$explicitPrice??'9');
        foreach($requests as $request)if(in_array($request['method'],['eth_call','eth_estimateGas'],true))same($request['params'][0]['gasPrice'],$explicitPrice===null?'0x9':'0x7');
    }
}
$corruptPath=tempnam(sys_get_temp_dir(),'decimal-corrupt-');
try {
    $store=new NonceManager($corruptPath);$key='202020:'.$auditSender;
    foreach(['[]','{}','{"bad":"1"}',json_encode([$key=>null]),json_encode([$key=>1]),json_encode([$key=>'18446744073709551616']),'{"'.$key.'":"10","'.$key.'":"0"}'] as $bad) {
        file_put_contents($corruptPath,$bad);rejects(fn()=>$store->reserve(202020,$auditSender,'0'));same(file_get_contents($corruptPath),$bad);
    }
} finally {unlink($corruptPath);if(file_exists($corruptPath.'.lock'))unlink($corruptPath.'.lock');}
echo "Core audit fee-context and nonce corruption regressions passed\n";
$auditPhrase='test test test test test test test test test test test junk';
same(Decimal\Mnemonic::wallet("\u{3000}".$auditPhrase."\u{3000}")->address,'0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266');
same(Decimal\Mnemonic::validate("\xff"),false);
rejects(fn()=>Decimal\Mnemonic::wallet($auditPhrase,passphrase:"\xff"));
rejects(fn()=>Decimal\SafeTransaction::create('0x'.str_repeat('0',40),202020,new TransactionDraft($auditSender,$auditTo),'0'));
rejects(fn()=>new Decimal\SafePolicy('0x'.str_repeat('0',40),202020,'0x1','0',[$auditSender=>'1'],'1'));
