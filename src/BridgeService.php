<?php
declare(strict_types=1);
namespace Decimal;

/** Bridge-v2 uint16 route ABI. Deployment/attestation are explicitly supplied by the caller. */
final class BridgeService
{
    private Contract $contract;
    public function __construct(private readonly Client $client, public readonly string $address)
    { $this->contract=Contract::profile($address,'PythonBridgeV2'); }
    private static function recipient(string $address): string
    { return '0x'.str_pad(substr(TransactionDraft::address($address),2),64,'0',STR_PAD_LEFT); }
    public function transferNative(string $sender,string $recipient,string $amountWei,string $serviceFeeWei,string $toChainId,string $nonce): TransactionDraft
    {
        Units::uint($amountWei);Units::uint($serviceFeeWei);$value=bcadd($amountWei,$serviceFeeWei,0);Units::uint($value);
        return $this->contract->draft($sender,'wrapAndTransferETH(bytes32,uint16,uint256,uint256)',[self::recipient($recipient),$toChainId,$nonce,$serviceFeeWei],$value);
    }
    public function transferToken(string $sender,string $token,string $recipient,string $amountRaw,string $serviceFeeWei,string $toChainId,string $nonce,bool $autoApprove=false,bool $resetExisting=false): array
    {
        $action=$this->contract->draft($sender,'transferTokens(address,bytes32,uint256,uint16,uint256)',[$token,self::recipient($recipient),$amountRaw,$toChainId,$nonce],$serviceFeeWei);
        if(!$autoApprove)return ['approvals'=>[],'action'=>$action];
        return Workflows::allowance($this->client,$token,$this->address,$amountRaw,$action,$resetExisting);
    }
    public function completeTransfer(string $sender,string $encodedVm,bool $unwrapWeth=false): TransactionDraft
    {
        if(!str_starts_with($encodedVm,'0x'))$encodedVm='0x'.$encodedVm;
        if($encodedVm==='0x')throw new \InvalidArgumentException('Encoded VM is required');
        return $this->contract->draft($sender,'completeTransfer(bytes,bool)',[$encodedVm,$unwrapWeth]);
    }
    public function minimumServiceFee(string $toChainId,string $block='latest'): string
    {
        $query=new Contract($this->address,[['type'=>'function','name'=>'minimalServiceFees','stateMutability'=>'view','inputs'=>[['name'=>'toChainId','type'=>'uint16']],'outputs'=>[['name'=>'','type'=>'uint256']]]]);
        return $query->call($this->client,'minimalServiceFees(uint16)',[$toChainId],$block)[0];
    }
    public function quote(string $toChainId,?string $nativeAmountWei=null,string $block='latest'): array
    {
        if($nativeAmountWei!==null)Units::uint($nativeAmountWei);
        $fee=$this->minimumServiceFee($toChainId,$block);$value=bcadd($nativeAmountWei??'0',$fee,0);Units::uint($value);
        return ['target_chain_id'=>$toChainId,'minimum_service_fee_wei'=>$fee,'call_value_wei'=>$value,'block'=>$block];
    }
}
