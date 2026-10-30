<?php
declare(strict_types=1);
namespace Decimal;

/** Legacy fee estimate, separate from nonce reservation and signing. */
final readonly class FeeQuote implements \JsonSerializable
{
    public function __construct(public TransactionDraft $draft,public string $estimatedGas,public string $gasLimit,public string $gasPriceWei,public string $nativeBalanceWei,public bool $exact=false)
    {
        foreach([$estimatedGas,$gasLimit,$gasPriceWei,$nativeBalanceWei] as $n)Units::uint($n);
        if($estimatedGas==='0'||bccomp($gasLimit,$estimatedGas,0)<0||bccomp($gasLimit,'18446744073709551615',0)>0)throw new \InvalidArgumentException('Invalid gas quote');
    }
    public function estimatedFee(): string { return bcmul($this->estimatedGas,$this->gasPriceWei,0); }
    public function maximumFee(): string { return bcmul($this->gasLimit,$this->gasPriceWei,0); }
    public function required(): string { return bcadd($this->draft->value,$this->maximumFee(),0); }
    public function missing(): string { return bccomp($this->required(),$this->nativeBalanceWei,0)>0?bcsub($this->required(),$this->nativeBalanceWei,0):'0'; }
    public function canAfford(): bool { return $this->missing()==='0'; }
    public function jsonSerialize(): array
    {
        return ['estimated_gas'=>$this->estimatedGas,'gas_limit'=>$this->gasLimit,'gas_price_wei'=>$this->gasPriceWei,'native_balance_wei'=>$this->nativeBalanceWei,'value_wei'=>$this->draft->value,'estimated_fee_wei'=>$this->estimatedFee(),'maximum_fee_wei'=>$this->maximumFee(),'required_wei'=>$this->required(),'missing_wei'=>$this->missing(),'can_afford'=>$this->canAfford(),'exact'=>$this->exact];
    }
}
