<?php
declare(strict_types=1);
rejects(fn()=>Decimal\TransactionDraft::burnDel($exWallet->address,'0'));
same($exResult->feeBudgetWei(),'46200');same($exResult->feeBudgetDel(),'0.0000000000000462');same($exResult->actualFeeWei(),null);
$refreshType='0x0';$refreshAvailable=true;$refreshCalls=[];
$refreshRpc=new Decimal\Rpc('http://unused',function($r)use(&$refreshType,&$refreshAvailable,&$refreshCalls){
    $refreshCalls[]=$r;$hash=$r['params'][0]??null;
    return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>match($r['method']){
        'eth_chainId'=>'0x31524',
        'eth_getTransactionReceipt'=>$refreshAvailable?['transactionHash'=>$hash,'status'=>'0x1','gasUsed'=>'0x5','blockNumber'=>'0x20','transactionIndex'=>'0x0']:null,
        'eth_getTransactionByHash'=>['hash'=>$hash,'type'=>$refreshType,'gasPrice'=>'0x7'],
        default=>throw new RuntimeException('Refresh attempted an unexpected RPC, signing or broadcast'),
    }];
});
$refreshClient=new Decimal\Client(Decimal\Network::testnet(),$refreshRpc);
$refreshed=$refreshClient->refreshResult($exResult);same($refreshed->isSuccessful(),true);same($refreshed->actualFeeWei(),'35');same($refreshed->feeBudgetWei(),'46200');same($refreshed->toArray()['raw_tx_hex'],$exFields['raw_tx_hex']);
$refreshType='0x1';same($refreshClient->refreshResult($exResult)->actualFeeWei(),'35');
$refreshType='0x2';same($refreshClient->refreshResult($exResult)->actualFeeWei(),null); // A dynamic maximum is not an actual paid price.
$refreshAvailable=false;same($refreshClient->refreshResult($exResult),$exResult);
rejects(fn()=>$refreshClient->refreshResult(Decimal\TransactionResult::failed('No hash')));
same($checksService->createdCheckHashes(['status'=>'0x1','logs'=>[$bcLog,$bcLog]]),['0x'.str_repeat('ab',32),'0x'.str_repeat('ab',32)]);
$redemption=Decimal\ChecksService::signRedemption($exWallet,'0x'.str_repeat('ab',32),$bcB);
same(Decimal\Wallet::recover(Decimal\ChecksService::redeemDigest('0x'.str_repeat('ab',32),$bcB),$redemption),$exWallet->address);
$sdk=new Decimal\Sdk($exClient);same(get_class($sdk->tokenEconomy($exTo)),Decimal\CurrentTokenEconomy::class);same(get_class($sdk->legacyTokenEconomy($exTo)),Decimal\TokenEconomy::class);same(get_class($sdk->nft($exTo,721,$exToken)),Decimal\NftCurrentOperations::class);same(get_class($sdk->rawNft($exTo,721)),Decimal\NftOperations::class);
$sequenceCalls=count($exCalls);same($ex->executeSequence([$exDraft,$exDraft],$exWallet)['atomic'],false);same(count($exCalls),$sequenceCalls);
rejects(fn()=>$ex->executeSequence([$exDraft,$exDraft],$exWallet,broadcast:true));same(count($exCalls),$sequenceCalls);
$sequenceRefresh=0;$sequence=$ex->executeSequence([$exDraft,$exDraft],$exWallet,function($index,$step)use(&$sequenceRefresh){same($index,1);++$sequenceRefresh;return $step;},true);
same($sequence['status'],'success');same($sequenceRefresh,1);same(count($sequence['results']),2);same($sequence['fee_budget_wei'],'92400');same($sequence['actual_fee_wei'],'84000');
echo "Current fee/refresh/checks helpers and current/legacy factory routing passed\n";
