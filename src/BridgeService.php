<?php
declare(strict_types=1);
namespace Decimal;

/** Bridge-v2 uint16 route ABI. Deployment/attestation are explicitly supplied by the caller. */
final class BridgeService
{
    private Contract $contract;
    private ?VerifiedContract $verified=null;
    private ?string $verifiedTargetChain=null;
    private ?string $lastVerifiedBlock=null;
    public function __construct(private readonly Client $client, public readonly string $address)
    { $this->contract=Contract::profile($address,'PythonBridgeV2'); }
    /** Caller-reviewed runtime evidence, plus an actual probe of the selected uint16 fee interface. */
    public static function verified(Client $client,string $address,string $runtimeHash,string $toChainId,?string $implementation=null,?string $implementationRuntimeHash=null): self
    {
        $service=new self($client,$address);
        // Validate route width before any remote query; never truncate EVM chain IDs.
        (new AbiCodec())->encodeParameters(['uint16'],[$toChainId]);
        $service->verified=new VerifiedContract($client,$address,'PythonBridgeV2',$runtimeHash,$implementation,$implementationRuntimeHash);
        $service->verifiedTargetChain=$toChainId;
        $service->minimumServiceFee($toChainId);
        return $service;
    }
    private function verifyRoute(string $toChainId): void
    {
        if($this->verifiedTargetChain!==null&&$this->verifiedTargetChain!==$toChainId)throw new \InvalidArgumentException('Bridge binding belongs to another destination route');
    }
    private function requireServiceFee(string $toChainId,string $serviceFeeWei): void
    {
        Units::uint($serviceFeeWei);
        if($this->verified!==null&&bccomp($serviceFeeWei,$this->minimumServiceFee($toChainId),0)<0)throw new \InvalidArgumentException('Service fee is below the verified reported minimum');
    }
    private function draft(string $sender,string $signature,array $args,string $value='0'): TransactionDraft
    { return $this->verified?->draft($sender,$signature,$args,$value)??$this->contract->draft($sender,$signature,$args,$value); }
    private static function recipient(string $address): string
    { return '0x'.str_pad(substr(TransactionDraft::address($address),2),64,'0',STR_PAD_LEFT); }
    public function transferNative(string $sender,string $recipient,string $amountWei,string $serviceFeeWei,string $toChainId,string $nonce): TransactionDraft
    {
        Units::uint($amountWei);Units::uint($serviceFeeWei);$value=bcadd($amountWei,$serviceFeeWei,0);Units::uint($value);
        $this->verifyRoute($toChainId);
        $this->requireServiceFee($toChainId,$serviceFeeWei);
        return $this->draft($sender,'wrapAndTransferETH(bytes32,uint16,uint256,uint256)',[self::recipient($recipient),$toChainId,$nonce,$serviceFeeWei],$value);
    }
    public function transferToken(string $sender,string $token,string $recipient,string $amountRaw,string $serviceFeeWei,string $toChainId,string $nonce,bool $autoApprove=false,bool $resetExisting=false): array
    {
        $this->verifyRoute($toChainId);
        if($autoApprove&&$this->verified===null)throw new \InvalidArgumentException('Bridge approval plans require a verified deployment binding');
        $this->requireServiceFee($toChainId,$serviceFeeWei);
        $action=$this->draft($sender,'transferTokens(address,bytes32,uint256,uint16,uint256)',[$token,self::recipient($recipient),$amountRaw,$toChainId,$nonce],$serviceFeeWei);
        if(!$autoApprove)return ['approvals'=>[],'action'=>$action];
        return Workflows::allowance($this->client,$token,$this->address,$amountRaw,$action,$resetExisting);
    }
    public function completeTransfer(string $sender,string $encodedVm,bool $unwrapWeth=false): TransactionDraft
    {
        if(!str_starts_with($encodedVm,'0x'))$encodedVm='0x'.$encodedVm;
        if($encodedVm==='0x')throw new \InvalidArgumentException('Encoded VM is required');
        return $this->draft($sender,'completeTransfer(bytes,bool)',[$encodedVm,$unwrapWeth]);
    }
    public function minimumServiceFee(string $toChainId,string $block='latest'): string
    {
        $this->verifyRoute($toChainId);
        if($this->verified!==null){
            if($block!=='latest')throw new \InvalidArgumentException('Verified bridge reads use the freshly verified block; use an explicit raw reader for historical probes');
            $block=$this->lastVerifiedBlock=$this->verified->verify();
        }
        $query=new Contract($this->address,[['type'=>'function','name'=>'minimalServiceFees','stateMutability'=>'view','inputs'=>[['name'=>'toChainId','type'=>'uint16']],'outputs'=>[['name'=>'','type'=>'uint256']]]]);
        return $query->call($this->client,'minimalServiceFees(uint16)',[$toChainId],$block)[0];
    }
    public function quote(string $toChainId,?string $nativeAmountWei=null,string $block='latest'): array
    {
        if($nativeAmountWei!==null)Units::uint($nativeAmountWei);
        $fee=$this->minimumServiceFee($toChainId,$block);$value=bcadd($nativeAmountWei??'0',$fee,0);Units::uint($value);
        return ['target_chain_id'=>$toChainId,'minimum_service_fee_wei'=>$fee,'call_value_wei'=>$value,'block'=>$this->verified!==null?$this->lastVerifiedBlock:$block];
    }
}
