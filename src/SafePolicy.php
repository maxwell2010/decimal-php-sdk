<?php
declare(strict_types=1);
namespace Decimal;

/** A pinned-block snapshot of the Decimal weighted Safe policy. */
final readonly class SafePolicy
{
    public function __construct(public string $safe, public int $chainId, public string $block, public string $nonce, public array $owners, public string $threshold)
    {
        if(TransactionDraft::address($safe)==='0x'.str_repeat('0',40)) throw new \InvalidArgumentException('Invalid Safe address');
        Units::decimal($block); Units::uint($nonce); Units::uint($threshold);
        if ($chainId < 1 || !$owners || $threshold === '0') throw new \InvalidArgumentException('Invalid Safe policy');
        $total = '0';
        foreach ($owners as $owner => $weight) {
            if ($owner !== TransactionDraft::address($owner) || $owner === '0x'.str_repeat('0',40)) throw new \InvalidArgumentException('Invalid Safe owner');
            Units::uint($weight);
            if ($weight === '0') throw new \InvalidArgumentException('Unsupported Safe weight');
            $total = bcadd($total,$weight,0); Units::uint($total);
        }
        if (bccomp($threshold,$total,0)>0) throw new \InvalidArgumentException('Safe threshold exceeds owner weight');
    }
    public static function read(Client $client, string $safe, string $block, string $profile='TestnetSafe20261029'): self
    {
        Units::decimal($block); $contract = Contract::profile($safe,$profile);
        $rows = $contract->call($client,'getOwnersWithWeights()',[],$block)[0]; $owners=[];
        foreach ($rows as [$owner,$weight]) {
            $owner=TransactionDraft::address($owner);
            if (isset($owners[$owner])) throw new \RuntimeException('Duplicate Safe owner');
            $owners[$owner]=$weight;
        }
        return new self($safe,$client->network->chainId,$block,$contract->call($client,'nonce()',[],$block)[0],$owners,$contract->call($client,'getWeightThreshold()',[],$block)[0]);
    }
    public function transaction(TransactionDraft $call): SafeTransaction { return SafeTransaction::create($this->safe,$this->chainId,$call,$this->nonce); }
    public function pack(SafeTransaction $tx, array $signatures): string
    {
        if (strtolower($tx->safe)!==strtolower($this->safe) || $tx->chainId!==$this->chainId || $tx->message['nonce']!==$this->nonce) throw new \InvalidArgumentException('Safe transaction outside snapshot');
        $rows=[]; $weight='0';
        foreach ($signatures as $sig) {
            $sig=SafeSignatures::validateEoa($sig);
            $v=hexdec(substr($sig,-2));
            if (!in_array($v,[0,1,27,28],true)) throw new \InvalidArgumentException('Only EIP-712 EOA signatures supported');
            $owner=Wallet::recover($tx->hash(),$sig);
            if (!isset($this->owners[$owner]) || isset($rows[$owner])) throw new \InvalidArgumentException('Unauthorized or duplicate Safe signer');
            $weight=bcadd($weight,$this->owners[$owner],0);
            $rows[$owner]=substr($sig,2,128).dechex($v<27?$v+27:$v);
        }
        if (bccomp($weight,$this->threshold,0)<0) throw new \InvalidArgumentException('Insufficient Safe signature weight');
        ksort($rows,SORT_STRING); return '0x'.implode('',$rows);
    }
    public function verifyHash(Client $client,SafeTransaction $tx,string $profile='TestnetSafe20261029'): void
    {
        if ($client->network->chainId!==$this->chainId || $tx->chainId!==$this->chainId || strtolower($tx->safe)!==strtolower($this->safe) || $tx->message['nonce']!==$this->nonce) throw new \InvalidArgumentException('Safe snapshot mismatch');
        $args=array_map(fn($f)=>$tx->message[$f['name']],SafeTransaction::types()['SafeTx']);
        $actual=Contract::profile($this->safe,$profile)->call($client,'getTransactionHash(address,uint256,bytes,uint8,uint256,uint256,uint256,address,address,uint256)',$args,$this->block)[0];
        if (strtolower($actual)!==strtolower($tx->hash())) throw new \RuntimeException('Safe on-chain transaction hash mismatch');
    }
    /** No delegatecall is permitted through this ordinary-call guarded path. */
    public function draft(Client $client,SafeTransaction $tx,string $sender,array $signatures,string $profile='TestnetSafe20261029'): TransactionDraft
    {
        if ($tx->message['operation']!=='0') throw new \InvalidArgumentException('Delegatecall requires a separately reviewed policy');
        $this->verifyHash($client,$tx,$profile);
        return $tx->draft($sender,$this->pack($tx,$signatures));
    }
}
