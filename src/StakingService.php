<?php
declare(strict_types=1);
namespace Decimal;
/** Decimal staking requests use human decimal amounts; plans never submit transactions. */
final class StakingService
{
    private Contract $contract;
    public function __construct(private readonly Client $client,string $delegation,private readonly string $wdel,private readonly string $masterValidator,string $profile='Delegation')
    {
        if(!in_array($profile,['Delegation','Delegation20261030'],true))throw new \InvalidArgumentException('Explicit staking profile required');
        TransactionDraft::address($wdel);TransactionDraft::address($masterValidator);$this->contract=Contract::profile($delegation,$profile);
    }
    private function positive(string $amount,int $decimals): string
    { $raw=Units::parse($amount,$decimals);Units::uint($raw);if($raw==='0')throw new \InvalidArgumentException('Positive stake amount required');return $raw; }
    private function timestamp(array $request,string $name): string
    { $v=$request[$name]??throw new \InvalidArgumentException('Missing '.$name);return Units::uint($v); }
    /** All request keys follow the Python catalog; sender replaces private_key. */
    public function plan(string $operation,string $sender,array $request,string $block='latest'): array
    {
        TransactionDraft::address($sender);
        if(str_starts_with($operation,'decimal.'))$operation=substr($operation,8);
        if(in_array($operation,['pause_self_validator','unpause_self_validator','pause_validator','unpause_validator'],true)){
            $self=str_contains($operation,'self');$unpause=str_starts_with($operation,'unpause');
            $signature=($unpause?'unpause':'pause').($self?'Self()':'Validator(address)');
            $draft=Contract::profile($this->masterValidator,'MasterValidator')->draft($sender,$signature,$self?[]:[$request['validator']]);
            $this->client->call($draft->rpc(),$block);return ['approvals'=>[],'action'=>$draft,'operation'=>$operation];
        }
        if($operation==='multisend_erc20')throw new \RuntimeException('Generic Multicall ERC20 spender is disabled; use an explicitly verified RestrictedMultisend');
        if($operation==='multisend_del'){
            $rows=$request['recipients']??[];$recipients=[];
            foreach($rows as $row)$recipients[]=['address'=>$row['to'],'amount'=>$this->positive($row['amount_del'],18)];
            $draft=Workflows::multisendDel($sender,$request['multicall'],$recipients,$request['memo']??'');
            $this->client->call($draft->rpc(),$block);return ['approvals'=>[],'action'=>$draft,'operation'=>$operation];
        }
        $valid=['delegate_del','delegate_erc20','hold_del','hold_erc20','unbond_del','unbond_erc20','withdraw_hold_del','withdraw_hold_erc20','transfer_stake_del','transfer_stake_erc20','stake_token_to_hold','reset_stake_hold','withdraw_stake_with_reset','withdraw_del_stake_with_reset','transfer_stake_with_reset','transfer_del_stake_with_reset','hold_stake_with_reset'];
        if(!in_array($operation,$valid,true))throw new \InvalidArgumentException('Unknown staking operation');
        $native=in_array($operation,['delegate_del','hold_del','unbond_del','withdraw_hold_del','transfer_stake_del','withdraw_del_stake_with_reset','transfer_del_stake_with_reset'],true);
        if(!$native&&$operation!=='reset_stake_hold'&&!is_string($request['token']??null))throw new \InvalidArgumentException('Token address required');
        if($operation!=='reset_stake_hold'&&!is_string($request[$native?'amount_del':'amount']??null))throw new \InvalidArgumentException('String stake amount required');
        if(str_starts_with($operation,'transfer_')&&!is_string($request['new_validator']??null))throw new \InvalidArgumentException('New validator required');
        if($operation==='reset_stake_hold'&&!is_string($request['delegator']??null))throw new \InvalidArgumentException('Delegator required');
        $token=$native?$this->wdel:($request['token']??$this->wdel);TransactionDraft::address($token);
        $validator=$request['validator']??$request['old_validator']??throw new \InvalidArgumentException('Validator required');TransactionDraft::address($validator);
        $amount='0';
        if($operation!=='reset_stake_hold'){
            $decimals=$native?18:($request['decimals']??null);
            if($decimals===null)$decimals=(int)Contract::profile($token,'Token')->call($this->client,'decimals()',[],$block)[0];
            if(!is_int($decimals)||$decimals<0||$decimals>255)throw new \InvalidArgumentException('Invalid token decimals');
            $amount=$this->positive($request[$native?'amount_del':'amount'],$decimals);
        }
        $reset=[];
        if(str_contains($operation,'with_reset')){
            $reset=$request['hold_timestamps_to_reset']??throw new \InvalidArgumentException('Reset timestamps required');
            if(!is_array($reset)||!array_is_list($reset))throw new \InvalidArgumentException('Positional reset timestamps required');
            foreach($reset as $t)Units::uint($t);
        }
        $deposit=in_array($operation,['delegate_del','delegate_erc20','hold_del','hold_erc20'],true);$hold=in_array($operation,['hold_del','hold_erc20'],true);
        $args=[$validator,$token,$amount];$value='0';
        if($deposit){
            if($native){$sig=$hold?'delegateHoldDEL(address,uint256)':'delegateDEL(address)';$args=[$validator];$value=$amount;}
            else $sig=$hold?'delegateHold(address,address,uint256,uint256)':'delegate(address,address,uint256)';
            if($hold)$args[]=$this->timestamp($request,'hold_timestamp');
        }elseif($operation==='reset_stake_hold'){
            if(($request['token']??null)===null){
                $legacyReset=new Contract($this->contract->address,[['type'=>'function','name'=>'resetHoldDEL','stateMutability'=>'nonpayable','inputs'=>[['type'=>'address'],['type'=>'address'],['type'=>'uint256']],'outputs'=>[]]]);
                $draft=$legacyReset->draft($sender,'resetHoldDEL(address,address,uint256)',[$validator,$request['delegator'],$this->timestamp($request,'hold_timestamp')]);
                $this->client->call($draft->rpc(),$block);
                return ['approvals'=>[],'action'=>$draft,'operation'=>$operation];
            }
            $sig='resetHold(address,address,address,uint256)';$args=[$validator,$request['delegator'],$token,$this->timestamp($request,'hold_timestamp')];
        }elseif($operation==='stake_token_to_hold'){
            $sig='hold(address,address,uint256,uint256,uint256)';$args[]=$this->timestamp($request,'old_hold_timestamp');$args[]=$this->timestamp($request,'new_hold_timestamp');
        }elseif($operation==='hold_stake_with_reset'){
            $sig='holdWithReset(address,address,uint256,uint256,uint256[])';$args[]=$this->timestamp($request,'new_hold_timestamp');$args[]=$reset;
        }elseif(str_starts_with($operation,'transfer_')){
            if(str_contains($operation,'with_reset')){$sig='transferWithReset(address,address,uint256,address,uint256[])';$args[]=$request['new_validator'];$args[]=$reset;}
            elseif(isset($request['hold_timestamp'])){$sig='transferHold(address,address,uint256,uint256,address)';$args[]=$this->timestamp($request,'hold_timestamp');$args[]=$request['new_validator'];}
            else{$sig='transfer(address,address,uint256,address)';$args[]=$request['new_validator'];}
        }elseif(str_contains($operation,'with_reset')){$sig='withdrawWithReset(address,address,uint256,uint256[])';$args[]=$reset;}
        elseif(str_starts_with($operation,'withdraw_hold')){$sig='withdrawHold(address,address,uint256,uint256)';$args[]=$this->timestamp($request,'hold_timestamp');}
        else $sig='withdraw(address,address,uint256)';
        $draft=$this->contract->draft($sender,$sig,$args,$value);$approvals=[];
        if($deposit&&!$native){
            $erc20=Contract::profile($token,'Token');
            if(bccomp($erc20->call($this->client,'balanceOf(address)',[$sender],$block)[0],$amount,0)<0)throw new \RuntimeException('Insufficient token balance');
            $allowance=$erc20->call($this->client,'allowance(address,address)',[$sender,$this->contract->address],$block)[0];
            if(bccomp($allowance,$amount,0)<0){
                if(!($request['auto_approve']??true))throw new \RuntimeException('Approval required');
                if($allowance!=='0'&&($request['reset_existing']??false))$approvals[]=$erc20->draft($sender,'approve(address,uint256)',[$this->contract->address,'0']);
                $approvals[]=$erc20->draft($sender,'approve(address,uint256)',[$this->contract->address,$amount]);
            }
        }
        if(!$approvals)$this->client->call($draft->rpc(),$block);
        return ['approvals'=>$approvals,'action'=>$draft,'operation'=>$operation,'requires_refresh'=>(bool)$approvals,'hold_timestamp'=>$request['new_hold_timestamp']??$request['hold_timestamp']??null];
    }
    /** C# non-atomic fallback. Execute reset, confirm, reread and replan transfer separately. */
    public function separateResetTransfer(string $sender,string $oldValidator,string $token,string $amount,int $decimals,string $newValidator,array $holdTimestamps): array
    {
        if(!array_is_list($holdTimestamps)||count(array_unique($holdTimestamps))!==count($holdTimestamps))throw new \InvalidArgumentException('Unique positional reset timestamps required');
        foreach($holdTimestamps as $time)Units::uint($time);
        $transfer=$this->contract->draft($sender,'transfer(address,address,uint256,address)',[$oldValidator,$token,$this->positive($amount,$decimals),$newValidator]);
        $steps=[];
        if(count($holdTimestamps)===1)$steps[]=$this->contract->draft($sender,'resetHold(address,address,address,uint256)',[$oldValidator,$sender,$token,$holdTimestamps[0]]);
        elseif($holdTimestamps)$steps[]=$this->contract->draft($sender,'resetHolds(address,address,address,uint256[])',[$oldValidator,$sender,$token,$holdTimestamps]);
        $steps[]=$transfer;
        return ['steps'=>$steps,'atomic'=>false,'requires_refresh'=>count($steps)>1];
    }
    /** Sign only after token domain, nonce, deadline and owner binding have been checked. */
    public function withPermit(Wallet $wallet,string $token,string $validator,string $amount,int $decimals,string $name,string $version,string $deadline,?string $holdTimestamp=null): array
    {
        $raw=$this->positive($amount,$decimals);
        if(bccomp(Contract::profile($token,'Token')->call($this->client,'balanceOf(address)',[$wallet->address])[0],$raw,0)<0)throw new \RuntimeException('Insufficient token balance');
        $permit=TypedData::checkedPermit($this->client,$wallet,$token,$name,$version,$this->contract->address,$raw,$deadline);
        $args=[$validator,$token,$raw];if($holdTimestamp!==null)$args[]=Units::uint($holdTimestamp);
        array_push($args,$deadline,$permit['v'],$permit['r'],$permit['s']);
        $signature=$holdTimestamp===null?'delegateByPermit(address,address,uint256,uint256,uint8,bytes32,bytes32)':'delegateHoldByPermit(address,address,uint256,uint256,uint256,uint8,bytes32,bytes32)';
        $draft=$this->contract->draft($wallet->address,$signature,$args);$this->client->simulate($draft);
        return ['approvals'=>[],'action'=>$draft,'permit'=>$permit];
    }
}
