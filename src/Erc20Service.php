<?php
declare(strict_types=1);
namespace Decimal;
/** Exact-unit ERC20 reads and drafts; no method sends transactions. */
final class Erc20Service
{
    private Contract $contract;
    public function __construct(private readonly Client $client,public readonly string $address) { $this->contract=Contract::profile($address,'Token'); }
    public function read(string $field,array $args=[],string $block='latest'): mixed { $sig=match($field){'name','symbol','decimals','totalSupply','version','DOMAIN_SEPARATOR'=>$field.'()','nonce'=>'nonces(address)',default=>throw new \InvalidArgumentException('Unknown ERC20 read')};if($field==='version'){ $version=new Contract($this->address,[['type'=>'function','name'=>'version','inputs'=>[],'outputs'=>[['type'=>'string','name'=>'']],'stateMutability'=>'view']]);return $version->call($this->client,$sig,$args,$block)[0]; }return $this->contract->call($this->client,$sig,$args,$block)[0]; }
    public function transferRaw(string $sender,string $recipient,string $amountRaw): TransactionDraft { return $this->contract->draft($sender,'transfer(address,uint256)',[$recipient,$amountRaw]); }
    public function transferFromRaw(string $sender,string $owner,string $recipient,string $amountRaw): TransactionDraft { return $this->contract->draft($sender,'transferFrom(address,address,uint256)',[$owner,$recipient,$amountRaw]); }
    public function info(string $block='latest'): array { $out=[];foreach(['name','symbol','decimals','totalSupply'] as $key)$out[$key]=$this->contract->call($this->client,$key.'()',[],$block)[0];return $out; }
    public function balance(string $owner,string $block='latest'): string { return $this->contract->call($this->client,'balanceOf(address)',[$owner],$block)[0]; }
    public function allowance(string $owner,string $spender,string $block='latest'): string { return $this->contract->call($this->client,'allowance(address,address)',[$owner,$spender],$block)[0]; }
    public function amount(string $amount,?int $decimals=null,string $block='latest'): string { if($decimals===null)$decimals=(int)$this->contract->call($this->client,'decimals()',[],$block)[0];return Units::parse($amount,$decimals); }
    public function approve(string $sender,string $spender,string $amount,?int $decimals=null,string $block='latest'): TransactionDraft { return $this->contract->draft($sender,'approve(address,uint256)',[$spender,$this->amount($amount,$decimals,$block)]); }
    public function transfer(string $sender,string $recipient,string $amount,?int $decimals=null,string $block='latest'): TransactionDraft { $raw=$this->amount($amount,$decimals,$block);$this->requireBalance($sender,$raw,$block);return $this->contract->draft($sender,'transfer(address,uint256)',[$recipient,$raw]); }
    public function transferFrom(string $sender,string $owner,string $recipient,string $amount,?int $decimals=null,string $block='latest'): TransactionDraft { $raw=$this->amount($amount,$decimals,$block);$this->requireBalance($owner,$raw,$block);if(bccomp($this->allowance($owner,$sender,$block),$raw,0)<0)throw new \RuntimeException('Insufficient ERC20 allowance');return $this->contract->draft($sender,'transferFrom(address,address,uint256)',[$owner,$recipient,$raw]); }
    public function requireBalance(string $owner,string $raw,string $block='latest'): void { Units::uint($raw);if(bccomp($this->balance($owner,$block),$raw,0)<0)throw new \RuntimeException('Insufficient token balance'); }
}
