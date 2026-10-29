<?php
declare(strict_types=1);
use Decimal\{Api,Indexer};
$auditAddress='0x'.str_repeat('12',20);
$auditIndexer=new Indexer(new Api('https://api.test/v1',fn($url)=>$url),new Api('https://index.test',fn($url)=>$url),new Api('https://root.test',fn($url)=>$url));
same($auditIndexer->coin('ibc/ABC123'),'https://api.test/v1/coins/ibc/ABC123');
rejects(fn()=>$auditIndexer->coin('ibc/../private'));
same($auditIndexer->coinRegistry(true),'https://api.test/v1/coins?with_price=1&pagination.limit=500');
same($auditIndexer->validators(),'https://api.test/v1/validators');
same($auditIndexer->walletStakes($auditAddress),'https://api.test/v1/validators/wallet/'.$auditAddress.'/stakes/coins');
same($auditIndexer->walletUnstakes($auditAddress),'https://api.test/v1/validators/'.$auditAddress.'/unstakes/coins');
same($auditIndexer->walletStakingIndex($auditAddress),'https://index.test/v1/wallets/'.$auditAddress.'/staking');
same($auditIndexer->health(),'https://root.test/health');
rejects(fn()=>(new Indexer(new Api('https://api.test')))->health());
rejects(fn()=>(new Indexer(new Api('https://api.test')))->walletStakingIndex($auditAddress));
echo "Indexer audit regression checks passed\n";
