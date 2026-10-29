<?php
declare(strict_types=1);
namespace Decimal;

final readonly class PreparedTransaction
{
    public function __construct(public TransactionDraft $draft, public int $chainId, public string $nonce, public string $gas, public string $gasPrice, public int $type = 0, public array $accessList = [], public string $maxPriorityFee = '0')
    {
        if ($chainId < 1) throw new \InvalidArgumentException('Invalid chain');
        foreach ([$nonce, $gas, $gasPrice] as $n) Units::uint($n);
        if ($gas === '0') throw new \InvalidArgumentException('Gas must be positive');
        if (!in_array($type,[0,1,2],true) || ($type===0 && $accessList!==[])) throw new \InvalidArgumentException('Invalid transaction type');
        Units::uint($maxPriorityFee);
        if ($type===2 && bccomp($maxPriorityFee,$gasPrice, 0)>0) throw new \InvalidArgumentException('Priority fee exceeds max fee');
        if (!array_is_list($accessList)) throw new \InvalidArgumentException('Invalid access list');
        foreach ($accessList as $row) {
            if (!is_array($row) || !isset($row['address'],$row['storageKeys']) || !is_array($row['storageKeys']) || !array_is_list($row['storageKeys'])) throw new \InvalidArgumentException('Invalid access entry');
            TransactionDraft::address($row['address']);
            foreach($row['storageKeys'] as $key) if(!is_string($key)||!preg_match('/^0x[0-9a-fA-F]{64}$/D',$key)) throw new \InvalidArgumentException('Invalid storage key');
        }
    }
    public function maximumFee(): string { return bcmul($this->gas, $this->gasPrice, 0); }
    public function fields(): array
    { return $this->draft->rpc() + ['chainId'=>$this->chainId, 'nonce'=>Units::hex($this->nonce), 'gas'=>Units::hex($this->gas), 'gasPrice'=>Units::hex($this->gasPrice)]; }
}
