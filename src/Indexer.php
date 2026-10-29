<?php
declare(strict_types=1);
namespace Decimal;
/** Explicit read API routes matching the Python indexer surface. Raw payloads remain available. */
final class Indexer
{
    public function __construct(private readonly Api $api, private readonly ?Api $walletStakingApi=null, private readonly ?Api $rootApi=null) {}
    private static function segment(string $value): string
    { if(!preg_match('/^[A-Za-z0-9_.-]+$/D',$value)||$value==='.'||$value==='..')throw new \InvalidArgumentException('Invalid API identifier');return $value; }
    private static function height(int $height): string { if($height<0)throw new \InvalidArgumentException('Negative height');return (string)$height; }
    public function latestBlock(): mixed { return $this->api->get('blocks/latest'); }
    public function block(int $height): mixed { return $this->api->get('blocks/'.self::height($height)); }
    public function blockTransactions(int $height,int $limit=50,int $offset=0): mixed { return $this->api->get('blocks/'.self::height($height).'/txs',Api::page($limit,$offset)); }
    public function transaction(string $hash): mixed { if(!preg_match('/^(?:0x)?[a-fA-F0-9]{64}$/D',$hash))throw new \InvalidArgumentException('Invalid transaction hash');return $this->api->get('txs/'.$hash); }
    public function transactions(int $limit=50,int $offset=0,array $filters=[]): mixed { return $this->api->get('txs',Api::page($limit,$offset)+$filters); }
    public function address(string $address): mixed { return $this->api->get('addresses/'.TransactionDraft::address($address)); }
    public function addressFull(string $address,bool $withErc20=true,?string $symbols=null): mixed { $q=['with_erc20'=>(int)$withErc20];if($symbols!==null)$q['symbols']=$symbols;return $this->api->get('addresses/'.TransactionDraft::address($address).'/full',$q); }
    public function addressTransactions(string $address,int $limit=50,int $offset=0): mixed { return $this->api->get('addresses/'.TransactionDraft::address($address).'/txs',Api::page($limit,$offset)); }
    public function walletBalances(string $address,int $limit=300,int $offset=0,bool $includeBank=true,bool $preferBank=true): mixed { return $this->api->get('erc20/balances/'.TransactionDraft::address($address),Api::page($limit,$offset)+['include_bank'=>(int)$includeBank,'prefer_bank'=>(int)$preferBank]); }
    public function coins(bool $withPrice=false,int $limit=100): mixed { Api::page($limit);return $this->api->get('coins',['with_price'=>(int)$withPrice,'pagination.limit'=>$limit]); }
    public function coin(string $denom): mixed { foreach(explode('/',$denom) as $part)self::segment($part);return $this->api->get('coins/'.$denom); }
    public function coinPrices(): mixed { return $this->api->get('coins/prices'); }
    public function validator(string $validator): mixed { return $this->api->get('validators/'.self::segment($validator)); }
    public function validatorDelegations(string $validator): mixed { return $this->api->get('validators/'.self::segment($validator).'/delegations'); }
    public function walletTransfers(string $address): mixed { return $this->api->get('validators/wallet/'.TransactionDraft::address($address).'/transfer/coins'); }
    public function rewards(string $delegator): mixed { return $this->api->get('rewards/delegators/'.self::segment($delegator)); }
    public function coinRegistry(bool $withPrice=false,int $limit=500): mixed { return $this->coins($withPrice,$limit); }
    public function validators(): mixed { return $this->api->validators(); }
    public function walletStakes(string $address): mixed { return $this->api->walletStakes($address); }
    public function walletUnstakes(string $address): mixed { return $this->api->walletUnstakes($address); }
    public function walletStakingIndex(string $address): mixed
    {
        if($this->walletStakingApi===null)throw new \RuntimeException('No wallet staking index URL configured');
        return $this->walletStakingApi->get('v1/wallets/'.TransactionDraft::address($address).'/staking');
    }
    public function health(): mixed
    {
        if($this->rootApi===null)throw new \RuntimeException('No root API URL configured');
        return $this->rootApi->get('health');
    }
    /** Verified discovery metadata, not a claim that a withdrawal is mature on chain. */
    public function withdrawalIndex(string $relativePath,?string $minimumBlock=null): array
    {
        if($minimumBlock!==null)Units::uint($minimumBlock);
        $payload=$this->api->get($relativePath);$r=$payload['Result']??null;
        if(!is_array($r)||($r['withdrawals_unavailable']??false)!==false||($r['withdrawals_authoritative']??null)!==true||($r['withdrawals_fresh']??null)!==true||!is_array($r['withdrawals']??null)||!array_is_list($r['withdrawals'])||count($r['withdrawals'])>10000)throw new \RuntimeException('Withdrawal index unavailable, incomplete or stale');
        $integer=static function($v): string {if(is_int($v)&&$v>=0)$v=(string)$v;if(!is_string($v))throw new \InvalidArgumentException('Invalid indexer integer');return Units::uint($v);};
        $block=$integer($r['withdrawals_checked_block']??null);
        if($minimumBlock!==null&&bccomp($block,$minimumBlock,0)<0)throw new \RuntimeException('Withdrawal index older than requested block');
        $entries=[];$seen=[];
        foreach($r['withdrawals'] as $row){$address=TransactionDraft::address($row['contract_address']??'');if($address==='0x'.str_repeat('0',40))throw new \InvalidArgumentException('Zero withdrawal contract');$id=$integer($row['id']??null);$key=$address.':'.$id;if(isset($seen[$key]))continue;$seen[$key]=true;$entries[]=['contract'=>$address,'index'=>$id];}
        return ['block'=>$block,'entries'=>$entries];
    }
}
