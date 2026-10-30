<?php
declare(strict_types=1);
namespace Decimal;
/** Read-only validator records and unsigned management requests. */
final class ValidatorService
{
    private Contract $contract;
    public function __construct(private readonly Client $client,string $address)
    {if(TransactionDraft::address($address)==='0x'.str_repeat('0',40))throw new \InvalidArgumentException('Zero validator contract');$this->contract=Contract::profile($address,'MasterValidator');}
    public static function resolved(Client $client): self {return new self($client,Deployment::resolve($client,'master-validator'));}
    public function read(string $query,?string $validator=null,string $block='latest'): mixed
    {
        $sig=match($query){'status'=>'getValidatorStatus(address)','active'=>'isActive(address)','member'=>'isMember(address)','validator'=>'getValidator(address)','penalties'=>'getValidatorPenalties(address)','reward'=>'getRewardPerBlock()','owner'=>'owner()','implementation'=>'getImpl()',default=>throw new \InvalidArgumentException('Unknown validator query')};
        $values=$this->contract->call($this->client,$sig,str_contains($sig,'address')?[$validator]:[],$block);
        if($query==='validator')return ['status'=>$values[0][0],'paused'=>$values[0][1],'penaltyPercentages'=>$values[0][2]];
        return $query==='reward'?['rewardPerBlock'=>$values[0],'enabled'=>$values[1]]:$values[0];
    }
    /** Owner evidence is caller-supplied, matching the C# offline draft API. */
    public function draft(string $operation,string $sender,array $request=[]): TransactionDraft
    {
        $required=match($operation){
            'add_candidate_del'=>['validator','meta','amount_del'],
            'add_validator_del'=>['validator','meta','amount_del','owner'],
            'add_candidate_token'=>['validator','meta','token','amount_raw'],
            'add_validator_token'=>['validator','meta','token','amount_raw','owner'],
            'add_validators'=>['owner','metas'],'approve_candidates'=>['owner','candidates'],
            'add_penalty'=>['owner','validator','penalty_percentage'],
            'pause','unpause','remove'=>['validator'],'update_meta'=>['validator','meta'],
            'pause_self','unpause_self'=>[],default=>throw new \InvalidArgumentException('Unknown validator operation')};
        foreach($required as $key){
            if(in_array($key,['metas','candidates'],true)){
                if(!is_array($request[$key]??null)||!array_is_list($request[$key]))throw new \InvalidArgumentException('Positional '.$key.' required');
            }elseif(!is_string($request[$key]??null))throw new \InvalidArgumentException('String '.$key.' required');
        }
        $ownerOps=['add_validator_del','add_validator_token','add_validators','approve_candidates','add_penalty'];
        if(in_array($operation,$ownerOps,true)&&!is_string($request['owner']??null))throw new \InvalidArgumentException('Explicit owner evidence required');
        if(in_array($operation,$ownerOps,true)&&TransactionDraft::address($request['owner'])!==TransactionDraft::address($sender))throw new \InvalidArgumentException('Sender must match supplied owner');
        if(isset($request['meta'])&&(!mb_check_encoding($request['meta'],'UTF-8')||trim($request['meta'])===''))throw new \InvalidArgumentException('Validator metadata required');
        $value='0';
        if(in_array($operation,['add_candidate_del','add_validator_del','add_candidate_token','add_validator_token'],true)){
            $native=str_ends_with($operation,'_del');$candidate=str_starts_with($operation,'add_candidate');
            $amount=$native?Units::parse($request['amount_del']):Units::uint($request['amount_raw']);if($amount==='0')throw new \InvalidArgumentException('Positive initial stake required');
            $args=[$request['validator'],$request['meta']];
            if($native){$sig=$candidate?'addCandidateDEL(address,string)':'addValidatorDEL(address,string)';$value=$amount;}
            else {if(TransactionDraft::address($request['token'])==='0x'.str_repeat('0',40))throw new \InvalidArgumentException('Zero token');$sig=$candidate?'addCandidate(address,string,(address,uint256))':'addValidator(address,string,(address,uint256))';$args[]=[$request['token'],$amount];}
        }else{
            [$sig,$args]=match($operation){
                'pause_self'=>['pauseSelf()',[]],'unpause_self'=>['unpauseSelf()',[]],
                'pause'=>['pauseValidator(address)',[$request['validator']]],'unpause'=>['unpauseValidator(address)',[$request['validator']]],
                'remove'=>['removeValidator(address)',[$request['validator']]],'update_meta'=>['updateValidatorMeta(address,string)',[$request['validator'],$request['meta']]],
                'add_validators'=>['addValidators((address,string)[])',[$request['metas']]],
                'approve_candidates'=>['approveCandidates(address[])',[$request['candidates']]],
                'add_penalty'=>['addPenalty(address,uint16)',[$request['validator'],$request['penalty_percentage']]],
                default=>throw new \InvalidArgumentException('Unknown validator operation')};
            if(in_array($operation,['add_validators','approve_candidates'],true)&&!$args[0])throw new \InvalidArgumentException('Nonempty validators required');
            if($operation==='add_validators')foreach($args[0] as $row)if(!is_array($row)||!array_is_list($row)||count($row)!==2||!is_string($row[1])||trim($row[1])==='')throw new \InvalidArgumentException('Validator metadata required');
        }
        return $this->contract->draft($sender,$sig,$args,$value);
    }
}
