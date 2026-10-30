<?php
declare(strict_types=1);
namespace Decimal;

/** Common prepare/sign/report orchestration. Broadcast always requires an explicit true. */
final class TransactionExecutor
{
    public function __construct(private readonly Client $client) {}
    public function estimateFeeForBurnDel(string $sender,string $amount,bool $exact=false): FeeQuote
    { return $this->client->estimateFee(TransactionDraft::burnDel($sender,$amount),$exact); }
    public function burnDel(string $sender,string $amount,#[\SensitiveParameter] Signer $signer,bool $broadcast=false,bool $waitReceipt=false,array $options=[]): TransactionResult
    { return $this->execute(TransactionDraft::burnDel($sender,$amount),$signer,$broadcast,$waitReceipt,$options); }
    public function prepare(TransactionDraft $draft,array $options=[]): PreparedTransaction
    {
        $allowed=['type','accessList','maxFeePerGas','maxPriorityFee','feeCap','nonces','baseFeeEstimate','cancellation'];
        foreach(array_keys($options) as $name)if(!in_array($name,$allowed,true))throw new \InvalidArgumentException('Unknown preparation option');
        return $this->client->prepare($draft,...$options);
    }
    public function execute(TransactionDraft $draft,#[\SensitiveParameter] Signer $signer,bool $broadcast=false,bool $waitReceipt=false,array $options=[],?string $eventProfile=null,array $metadata=[]): TransactionResult
    {
        if($waitReceipt&&!$broadcast)throw new \InvalidArgumentException('Cannot wait for an unbroadcast transaction');
        if(TransactionDraft::address($draft->from)!==TransactionDraft::address($signer->address()))throw new \InvalidArgumentException('Signer does not match sender');
        if($eventProfile!==null)Contract::profile($draft->to??throw new \InvalidArgumentException('Event emitter required'),$eventProfile);
        $prepared=$this->prepare($draft,$options);$signed=$signer->sign($prepared);
        if($signed->chainId!==$prepared->chainId||$signed->unsignedHash!==$prepared->signingHash()||$signed->sender!==TransactionDraft::address($draft->from))throw new \RuntimeException('Signer changed transaction');
        ($options['cancellation']??null)?->throwIfCancelled();
        if($broadcast)$this->client->broadcast($signed);
        $receipt=$waitReceipt?$this->client->waitReceipt($signed->hash,cancellation:$options['cancellation']??null):null;
        $events=$eventProfile!==null&&$receipt!==null?Events::receipt($eventProfile,$receipt,$draft->to??throw new \InvalidArgumentException('Event emitter required')):[];
        return TransactionResult::signed($prepared,$signed,$broadcast,$receipt,$events,$metadata);
    }
    /** Rebuild only after every approval has a successful receipt; never pre-sign a dependent action. */
    public function executePlan(TransactionPlan|TransactionDraft|array $input,#[\SensitiveParameter] Signer $signer,?\Closure $refresh=null,bool $broadcast=false,bool $waitReceipt=false,array $options=[],?string $eventProfile=null): array
    {
        $plan=TransactionPlan::from($input);
        if($eventProfile!==null)Contract::profile($plan->action->to??throw new \InvalidArgumentException('Event emitter required'),$eventProfile);
        if(!$plan->requiresApprovals())return ['status'=>'action','approvals'=>[],'action'=>$this->execute($plan->action,$signer,$broadcast,$waitReceipt,$options,$eventProfile,$plan->metadata)];
        if(!$broadcast){
            if($waitReceipt)throw new \InvalidArgumentException('Cannot wait for unbroadcast approvals');
            return ['status'=>'requires_approvals','approvals'=>$plan->approvals,'action'=>null,'plan'=>$plan];
        }
        if($refresh===null)throw new \InvalidArgumentException('Approval plans require an explicit state refresh before broadcasting');
        $results=[];
        foreach($plan->approvals as $approval){
            $result=$this->execute($approval,$signer,true,true,$options);
            if(!$result->isSuccessful())throw new \RuntimeException('Approval failed; dependent action was not signed');
            $results[]=$result;
        }
        $updated=TransactionPlan::from($refresh());
        if($updated->requiresApprovals())throw new \RuntimeException('Approval remains insufficient after confirmed steps; action was not signed');
        if($updated->action->rpc()!==$plan->action->rpc())throw new \RuntimeException('Action changed after refresh; review a new plan before signing');
        return ['status'=>'action','approvals'=>$results,'action'=>$this->execute($updated->action,$signer,true,$waitReceipt,$options,$eventProfile,$updated->metadata)];
    }
    /** Non-atomic multi-action requests such as identity then cap update, or reset then transfer. */
    public function executeSequence(array $steps,#[\SensitiveParameter] Signer $signer,?\Closure $refresh=null,bool $broadcast=false,array $options=[]): array
    {
        if(!array_is_list($steps)||!$steps||count($steps)>500)throw new \InvalidArgumentException('1..500 positional sequence steps required');
        foreach($steps as $step)if(!$step instanceof TransactionDraft||TransactionDraft::address($step->from)!==TransactionDraft::address($signer->address()))throw new \InvalidArgumentException('Sequence sender mismatch');
        if(!$broadcast)return ['status'=>'requires_sequential_execution','steps'=>$steps,'atomic'=>false];
        if(count($steps)>1&&$refresh===null)throw new \InvalidArgumentException('Multi-action execution requires an explicit refresh between confirmed steps');
        $results=[];$budget='0';$paid='0';
        foreach($steps as $index=>$step){
            if($index>0){$updated=$refresh($index,$step);if(!$updated instanceof TransactionDraft||$updated->rpc()!==$step->rpc())throw new \RuntimeException('Sequence step changed after refresh; review remaining actions');$step=$updated;}
            $result=$this->execute($step,$signer,true,true,$options);$results[]=$result;
            if(!$result->isSuccessful())throw new \RuntimeException('Sequence step failed; later actions were not signed');
            $budget=bcadd($budget,$result->feeBudgetWei(),0);$fee=$result->actualFeeWei();$paid=$paid===null||$fee===null?null:bcadd($paid,$fee,0);
        }
        return ['status'=>'success','atomic'=>false,'results'=>$results,'fee_budget_wei'=>$budget,'actual_fee_wei'=>$paid];
    }
}
