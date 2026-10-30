<?php
declare(strict_types=1);
namespace Decimal;
/** Original NFT position contract; never redirects legacy reads/exits to unified. */
final class NftLegacyStaking
{
 private Contract $contract;
 public function __construct(private readonly Client $client,string $address)
 { $abi=json_decode(file_get_contents(dirname(__DIR__).'/abi/DelegationNFT.json'),true,512,JSON_THROW_ON_ERROR);foreach(['resetHold'=>['address','address','address','uint256','uint256'],'resetHolds'=>['address','address','address','uint256','uint256[]'],'withdrawWithReset'=>['address','address','uint256','uint256','uint256[]'],'transferWithReset'=>['address','address','uint256','uint256','address','uint256[]'],'holdWithReset'=>['address','address','uint256','uint256','uint256','uint256[]']] as $name=>$types)$abi[]=['type'=>'function','name'=>$name,'stateMutability'=>'nonpayable','inputs'=>array_map(fn($t)=>['name'=>'','type'=>$t],$types),'outputs'=>[]];$this->contract=new Contract($address,$abi); }
 public function plan(string $operation,string $sender,array $r): TransactionDraft
 {
  $required=match($operation){
   'stake_to_hold'=>['validator','nft','token_id','amount','old_hold_timestamp','new_hold_timestamp'],
   'reset_stake_hold'=>['validator','delegator','nft','token_id','hold_timestamp'],
   'reset_stake_holds'=>['validator','delegator','nft','token_id','hold_timestamps'],
   'withdraw_with_reset'=>['validator','nft','token_id','amount','hold_timestamps_to_reset'],
   'transfer_with_reset'=>['validator','nft','token_id','amount','new_validator','hold_timestamps_to_reset'],
   'hold_with_reset'=>['validator','nft','token_id','amount','new_hold_timestamp','hold_timestamps_to_reset'],
   'complete_stake'=>['indexes'],default=>throw new \InvalidArgumentException('Unknown legacy NFT operation')};
  foreach($required as $key)if(!array_key_exists($key,$r)||$r[$key]===null)throw new \InvalidArgumentException('Missing NFT request field: '.$key);
  if(isset($r['old_hold_timestamp']))Units::uint($r['old_hold_timestamp']);
  $base=[$r['validator']??null,$r['nft']??null,$r['token_id']??null];
  $amount=$r['amount']??null;if($amount!==null){Units::uint($amount);if($amount==='0')throw new \InvalidArgumentException('Positive NFT amount required');}
  $times=$operation==='complete_stake'?$r['indexes']:($operation==='reset_stake_holds'?$r['hold_timestamps']:($r['hold_timestamps_to_reset']??[]));if(!array_is_list($times))throw new \InvalidArgumentException('Positional NFT timestamps/indices required');foreach($times as $t)Units::uint($t);if(in_array($operation,['reset_stake_holds','withdraw_with_reset','transfer_with_reset','hold_with_reset','complete_stake'],true)&&(!$times||count(array_unique($times))!==count($times)))throw new \InvalidArgumentException('Unique nonempty timestamps/indices required');foreach(['hold_timestamp','new_hold_timestamp'] as $name)if(isset($r[$name])){Units::uint($r[$name]);if($r[$name]==='0')throw new \InvalidArgumentException('Positive hold timestamp required');}
  if($operation==='stake_to_hold'){if(bccomp($r['new_hold_timestamp'],$r['old_hold_timestamp'],0)<=0)throw new \InvalidArgumentException('Hold time must increase');$sig='hold(address,address,uint256,uint256,uint256,uint256)';$args=[...$base,$amount,$r['old_hold_timestamp'],$r['new_hold_timestamp']];}
  elseif($operation==='reset_stake_hold'){$sig='resetHold(address,address,address,uint256,uint256)';$args=[$r['validator'],$r['delegator'],$r['nft'],$r['token_id'],$r['hold_timestamp']];}
  elseif($operation==='reset_stake_holds'){$sig='resetHolds(address,address,address,uint256,uint256[])';$args=[$r['validator'],$r['delegator'],$r['nft'],$r['token_id'],$times];}
  elseif($operation==='withdraw_with_reset'){$sig='withdrawWithReset(address,address,uint256,uint256,uint256[])';$args=[...$base,$amount,$times];}
  elseif($operation==='transfer_with_reset'){if(TransactionDraft::address($r['validator'])===TransactionDraft::address($r['new_validator']))throw new \InvalidArgumentException('New validator must differ');$sig='transferWithReset(address,address,uint256,uint256,address,uint256[])';$args=[...$base,$amount,$r['new_validator'],$times];}
  elseif($operation==='hold_with_reset'){$sig='holdWithReset(address,address,uint256,uint256,uint256,uint256[])';$args=[...$base,$amount,$r['new_hold_timestamp'],$times];}
  elseif($operation==='complete_stake'){if(!$times)throw new \InvalidArgumentException('NFT indices required');$sig='complete(uint256[])';$args=[$times];}
  else throw new \InvalidArgumentException('Unknown legacy NFT operation');
  return $this->contract->draft($sender,$sig,$args);
 }
 public function stake(string $validator,string $delegator,string $nft,string $id,string $block='latest'): StakePosition
 { return $this->position($this->contract->call($this->client,'getStake(address,address,address,uint256)',[$validator,$delegator,$nft,$id],$block)[0],$validator,$delegator,$nft,$id,'0'); }
 public function holdStake(string $validator,string $delegator,string $nft,string $id,string $timestamp,string $block='latest'): StakePosition
 { if($timestamp==='0')throw new \InvalidArgumentException('Positive hold key required');return $this->position($this->contract->call($this->client,'getHoldStake(address,address,address,uint256,uint256)',[$validator,$delegator,$nft,$id,$timestamp],$block)[0],$validator,$delegator,$nft,$id,$timestamp); }
 public function stakeId(string $validator,string $delegator,string $nft,string $id,?string $timestamp=null,string $block='latest'): string
 { return $this->contract->call($this->client,$timestamp===null?'getStakeId(address,address,address,uint256)':'getHoldStakeId(address,address,address,uint256,uint256)',$timestamp===null?[$validator,$delegator,$nft,$id]:[$validator,$delegator,$nft,$id,$timestamp],$block)[0]; }
 public function frozen(string $index,string $block='latest'): FrozenStake
 { $r=$this->contract->call($this->client,'getFrozenStake(uint256)',[$index],$block)[0];$p=new StakePosition($r[0]);if($p->exists()&&!in_array($p->fields['tokenType'],['2','3'],true))throw new \RuntimeException('Non-NFT frozen position');return new FrozenStake($index,$p->fields,$r[1],$r[2],$r[3],$block); }
 public function frozenMany(array $indices,string $block='latest'): array
 { if(!array_is_list($indices)||!$indices||count(array_unique($indices))!==count($indices))throw new \InvalidArgumentException('Unique nonempty indices required');$rows=$this->contract->call($this->client,'getFrozenStakes(uint256[])',[$indices],$block)[0];if(count($rows)!==count($indices))throw new \RuntimeException('Frozen NFT count mismatch');$out=[];foreach($rows as $i=>$r){$p=new StakePosition($r[0]);if($p->exists()&&!in_array($p->fields['tokenType'],['2','3'],true))throw new \RuntimeException('Non-NFT frozen position');$out[]=new FrozenStake($indices[$i],$p->fields,$r[1],$r[2],$r[3],$block);}return $out; }
 public function freezeTime(string $freezeType,string $block='latest'): string
 { if(!in_array($freezeType,['1','2'],true))throw new \InvalidArgumentException('Withdrawal/transfer freeze type required');return $this->contract->call($this->client,'getFreezeTime(uint8)',[$freezeType],$block)[0]; }
 private function position(array $tuple,string $validator,string $delegator,string $nft,string $id,string $hold): StakePosition
 { $p=new StakePosition($tuple);$f=$p->fields;if(array_slice($tuple,0,7)!==['0x'.str_repeat('0',40),'0x'.str_repeat('0',40),'0x'.str_repeat('0',40),'0','0','0','0']&&(!in_array($f['tokenType'],['2','3'],true)||TransactionDraft::address($f['validator'])!==TransactionDraft::address($validator)||TransactionDraft::address($f['delegator'])!==TransactionDraft::address($delegator)||TransactionDraft::address($f['token'])!==TransactionDraft::address($nft)||$f['tokenId']!==$id||$f['holdTimestamp']!==$hold))throw new \RuntimeException('NFT stake response key mismatch');return $p; }
}
