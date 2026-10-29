<?php
declare(strict_types=1);
namespace Decimal;

/** Rechecks caller-trusted deployment evidence before every read or draft. */
final readonly class VerifiedContract
{
    public function __construct(private Client $client,public string $address,private string $profile,private string $runtimeHash,private ?string $implementation=null,private ?string $implementationRuntimeHash=null)
    {
        if (($implementation===null)!==($implementationRuntimeHash===null)) throw new \InvalidArgumentException('Proxy binding requires implementation and its runtime hash');
        $this->verify(); Contract::profile($address,$profile);
    }
    public function verify(): string
    {
        $this->client->verifyChain(); $block=$this->client->rpc->call('eth_blockNumber'); Units::decimal($block);
        Deployment::verify($this->client,$this->address,$this->runtimeHash,$this->implementation,$block);
        if($this->implementation===null && Deployment::implementation($this->client,$this->address,$block)!==null) throw new \RuntimeException('Proxy binding requires trusted implementation address and runtime hash');
        if ($this->implementation!==null) Deployment::verify($this->client,$this->implementation,$this->implementationRuntimeHash,null,$block);
        return $block;
    }
    public function draft(string $sender,string $signature,array $args=[],string $value='0'): TransactionDraft
    { $this->verify(); return Contract::profile($this->address,$this->profile)->draft($sender,$signature,$args,$value); }
    public function call(string $signature,array $args=[]): array
    { $block=$this->verify(); return Contract::profile($this->address,$this->profile)->call($this->client,$signature,$args,$block); }
}
