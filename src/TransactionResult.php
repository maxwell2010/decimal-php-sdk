<?php
declare(strict_types=1);
namespace Decimal;

/** Portable report. Amounts and receipt quantities are lossless decimal strings. */
final readonly class TransactionResult implements \JsonSerializable
{
    private function __construct(private array $fields) {}
    public static function signed(PreparedTransaction $prepared,SignedTransaction $signed,bool $broadcasted=false,?array $receipt=null,array $events=[],array $metadata=[]): self
    {
        if($signed->chainId!==$prepared->chainId||$signed->unsignedHash!==$prepared->signingHash()||$signed->sender!==TransactionDraft::address($prepared->draft->from))throw new \InvalidArgumentException('Signed transaction does not match preparation');
        if($receipt!==null&&(!$broadcasted||strtolower($receipt['transactionHash']??'')!==$signed->hash||!in_array($receipt['status']??null,['0x0','0x1'],true)))throw new \InvalidArgumentException('Receipt identity/status mismatch');
        $quantity=static fn(string $key): ?string=>isset($receipt[$key])?Units::decimal($receipt[$key]):null;
        $gasUsed=$quantity('gasUsed');$price=$quantity('effectiveGasPrice');
        $fee=$gasUsed!==null&&$price!==null?bcmul($gasUsed,$price,0):null;
        return new self([
            'success'=>$receipt===null||$receipt['status']==='0x1',
            'status'=>$receipt!==null?($receipt['status']==='0x1'?'success':'failed'):($broadcasted?'pending':'dry_run'),
            'tx_hash'=>$signed->hash,'broadcasted'=>$broadcasted,'raw_tx_hex'=>$signed->raw,
            'block_number'=>$quantity('blockNumber'),'transaction_index'=>$quantity('transactionIndex'),
            'gas_used'=>$gasUsed,'effective_gas_price_wei'=>$price,'effective_fee_wei'=>$fee,
            'effective_fee_del'=>$fee===null?null:Units::format($fee),
            'gas'=>$prepared->gas,'estimated_gas'=>$prepared->estimatedGas,'fee_wei'=>$prepared->maximumFee(),
            'fee_del'=>Units::format($prepared->maximumFee()),'estimated_fee_wei'=>$prepared->estimatedFee(),
            'receipt'=>$receipt,'events'=>$events,'error'=>null,'metadata'=>$metadata,
            'oracle_gas_price_wei'=>$metadata['oracle_gas_price_wei']??null,
            'token_address'=>$metadata['token_address']??null,'hold_timestamp'=>$metadata['hold_timestamp']??null,'hold_time'=>$metadata['hold_time']??null,
            'user_message'=>$metadata['user_message']??null,'native_balance_wei'=>$metadata['native_balance_wei']??null,
            'required_wei'=>bcadd($prepared->draft->value,$prepared->maximumFee(),0),'missing_wei'=>'0',
            'token_balance_raw'=>$metadata['token_balance_raw']??null,'token_required_raw'=>$metadata['token_required_raw']??null,
            'token_missing_raw'=>$metadata['token_missing_raw']??null,'token_allowance_raw'=>$metadata['token_allowance_raw']??null,
            'token_allowance_required_raw'=>$metadata['token_allowance_required_raw']??null,'token_allowance_missing_raw'=>$metadata['token_allowance_missing_raw']??null,
        ]);
    }
    public static function failed(string $message): self
    { return new self(['success'=>false,'status'=>'failed','error'=>$message,'tx_hash'=>null,'broadcasted'=>false,'receipt'=>null]); }
    public static function fromReceipt(string $hash,?array $receipt): self
    {
        if(!preg_match('/^0x[0-9a-fA-F]{64}$/D',$hash))throw new \InvalidArgumentException('Expected transaction hash');
        if($receipt!==null&&(strtolower($receipt['transactionHash']??'')!==strtolower($hash)||!in_array($receipt['status']??null,['0x0','0x1'],true)))throw new \InvalidArgumentException('Receipt identity/status mismatch');
        $q=static fn(string $key): ?string=>isset($receipt[$key])?Units::decimal($receipt[$key]):null;
        $gas=$q('gasUsed');$price=$q('effectiveGasPrice');$fee=$gas===null||$price===null?null:bcmul($gas,$price,0);
        return new self(['success'=>$receipt===null?null:$receipt['status']==='0x1','status'=>$receipt===null?'pending':($receipt['status']==='0x1'?'success':'failed'),'tx_hash'=>strtolower($hash),'broadcasted'=>null,'receipt'=>$receipt,'block_number'=>$q('blockNumber'),'transaction_index'=>$q('transactionIndex'),'gas_used'=>$gas,'effective_gas_price_wei'=>$price,'effective_fee_wei'=>$fee,'effective_fee_del'=>$fee===null?null:Units::format($fee)]);
    }
    public function isConfirmed(): bool { return ($this->fields['receipt']??null)!==null; }
    public function isSuccessful(): bool { return ($this->fields['status']??null)==='success'; }
    public function isPending(): bool { return ($this->fields['status']??null)==='pending'; }
    public function feeBudgetWei(): ?string { return $this->fields['fee_wei']??null; }
    public function feeBudgetDel(): ?string { return $this->fields['fee_del']??null; }
    public function actualFeeWei(): ?string { return $this->fields['effective_fee_wei']??null; }
    public function actualFeeDel(): ?string { return $this->fields['effective_fee_del']??null; }
    public function refreshed(array $receipt,?string $legacyGasPrice=null): self
    {
        $fresh=self::fromReceipt($this->fields['tx_hash']??'',$receipt)->toArray();
        if($fresh['gas_used']!==null&&$fresh['effective_gas_price_wei']===null&&$legacyGasPrice!==null){
            Units::uint($legacyGasPrice);$fresh['effective_gas_price_wei']=$legacyGasPrice;
            $fresh['effective_fee_wei']=bcmul($fresh['gas_used'],$legacyGasPrice,0);$fresh['effective_fee_del']=Units::format($fresh['effective_fee_wei']);
        }
        return new self(array_replace($this->fields,$fresh,['broadcasted'=>true]));
    }
    public function toArray(): array { return $this->fields; }
    public function jsonSerialize(): array { return $this->fields; }
}
