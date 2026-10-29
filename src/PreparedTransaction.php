<?php
declare(strict_types=1);
namespace Decimal;

final readonly class PreparedTransaction
{
    public function __construct(public TransactionDraft $draft, public int $chainId, public string $nonce, public string $gas, public string $gasPrice, public int $type = 0, public array $accessList = [], public string $maxPriorityFee = '0', public ?string $estimatedGas = null, public ?string $baseFeeEstimate = null)
    {
        if ($chainId < 1) throw new \InvalidArgumentException('Invalid chain');
        foreach ([$nonce, $gas, $gasPrice] as $n) Units::uint($n);
        if ($gas === '0') throw new \InvalidArgumentException('Gas must be positive');
        if(bccomp($gas,'18446744073709551615',0)>0) throw new \InvalidArgumentException('Gas exceeds uint64');
        if(bccomp($nonce,'18446744073709551615',0)>0) throw new \InvalidArgumentException('Nonce exceeds uint64');
        if (!in_array($type,[0,1,2],true) || ($type===0 && $accessList!==[])) throw new \InvalidArgumentException('Invalid transaction type');
        Units::uint($maxPriorityFee);
        if ($type===2 && bccomp($maxPriorityFee,$gasPrice, 0)>0) throw new \InvalidArgumentException('Priority fee exceeds max fee');
        if($type!==2 && $maxPriorityFee!=='0') throw new \InvalidArgumentException('Priority fee is only valid for EIP-1559');
        if($estimatedGas!==null) { Units::uint($estimatedGas); if($estimatedGas==='0'||bccomp($estimatedGas,$gas,0)>0) throw new \InvalidArgumentException('Estimated gas exceeds reserved gas'); }
        if($baseFeeEstimate!==null) {
            Units::uint($baseFeeEstimate);
            if($type!==2||bccomp($baseFeeEstimate,$gasPrice,0)>0) throw new \InvalidArgumentException('Invalid dynamic base fee estimate');
        }
        if (!array_is_list($accessList)) throw new \InvalidArgumentException('Invalid access list');
        foreach ($accessList as $row) {
            if (!is_array($row) || !isset($row['address'],$row['storageKeys']) || !is_array($row['storageKeys']) || !array_is_list($row['storageKeys'])) throw new \InvalidArgumentException('Invalid access entry');
            TransactionDraft::address($row['address']);
            foreach($row['storageKeys'] as $key) if(!is_string($key)||!preg_match('/^0x[0-9a-fA-F]{64}$/D',$key)) throw new \InvalidArgumentException('Invalid storage key');
        }
    }
    public function maximumFee(): string { return bcmul($this->gas, $this->gasPrice, 0); }
    public function estimatedFee(): ?string
    {
        if($this->type===2 && $this->baseFeeEstimate===null) return null;
        $price=$this->gasPrice;
        if($this->type===2) {
            $effective=bcadd($this->baseFeeEstimate,$this->maxPriorityFee,0);
            if(bccomp($effective,$price,0)<0) $price=$effective;
        }
        return bcmul($this->estimatedGas??$this->gas,$price,0);
    }
    public function fields(): array
    {
        $fields=$this->draft->rpc()+['chainId'=>$this->chainId,'nonce'=>Units::hex($this->nonce),'gas'=>Units::hex($this->gas)];
        if($this->type===2) $fields+=['maxFeePerGas'=>Units::hex($this->gasPrice),'maxPriorityFeePerGas'=>Units::hex($this->maxPriorityFee)];
        else $fields['gasPrice']=Units::hex($this->gasPrice);
        if($this->type!==0) $fields+=['type'=>Units::hex((string)$this->type),'accessList'=>$this->accessList];
        return $fields;
    }
    public function signingPayload(): array
    {
        $q=static fn(string $n):string=>$n==='0'?'':Units::hex($n);
        $to=$this->draft->to??''; $data=$this->draft->data==='0x'?'':$this->draft->data;
        if($this->type===0) return [$q($this->nonce),$q($this->gasPrice),$q($this->gas),$to,$q($this->draft->value),$data,Units::hex((string)$this->chainId),'',''];
        $access=array_map(static fn($r)=>[$r['address'],$r['storageKeys']],$this->accessList);
        return [Units::hex((string)$this->chainId),$q($this->nonce),...($this->type===2?[$q($this->maxPriorityFee),$q($this->gasPrice)]:[$q($this->gasPrice)]),$q($this->gas),$to,$q($this->draft->value),$data,$access];
    }
    public function signingHash(): string
    {
        $prefix=$this->type===0?'':sprintf('%02x',$this->type);
        return '0x'.\kornrunner\Keccak::hash(hex2bin($prefix.(new Rlp())->encode($this->signingPayload())),256);
    }
}
