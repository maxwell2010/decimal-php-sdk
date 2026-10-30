<?php
declare(strict_types=1);
namespace Decimal;
/** Unified NFTStake is a distinct ten-field structure, never the fungible Stake. */
final class NftStakingReader
{
 private Contract $contract;
 public function __construct(private readonly Client $client,string $deployment)
 { $this->contract=Contract::profile($deployment,'Delegation20261030'); }
 public function stake(string $nft,string $id,string $delegator,string $validator,string $holdTimestamp='0',string $block='latest'): array
 {
  $entry=$this->contract->function('getNFTStake(address,uint256,address,address,uint256)');
  $tuple=$this->contract->call($this->client,'getNFTStake(address,uint256,address,address,uint256)',[$nft,$id,$delegator,$validator,$holdTimestamp],$block)[0];
  if($tuple[9]&&(!in_array($tuple[3],['2','3'],true)||TransactionDraft::address($tuple[0])!==TransactionDraft::address($nft)||$tuple[1]!==$id||TransactionDraft::address($tuple[4])!==TransactionDraft::address($delegator)||TransactionDraft::address($tuple[5])!==TransactionDraft::address($validator)||$tuple[8]!==$holdTimestamp))throw new \RuntimeException('Unified NFT stake does not match requested position keys');
  return array_combine(array_column($entry['outputs'][0]['components'],'name'),$tuple);
 }
 public function stakeId(string $nft,string $id,string $delegator,string $validator,string $holdTimestamp='0',string $block='latest'): string
 { return $this->contract->call($this->client,'getNFTStakeId(address,uint256,address,address,uint256)',[$nft,$id,$delegator,$validator,$holdTimestamp],$block)[0]; }
 public function frozen(string $index,string $block='latest'): FrozenStake
 { $r=$this->contract->call($this->client,'getFrozenStake(uint256)',[$index],$block)[0];return $this->frozenRow($index,$r,$block); }
 public function frozenMany(array $indices,string $block='latest'): array
 { if(!array_is_list($indices)||!$indices||count(array_unique($indices))!==count($indices))throw new \InvalidArgumentException('Unique nonempty NFT indices required');$rows=$this->contract->call($this->client,'getFrozenStakes(uint256[])',[$indices],$block)[0];if(count($rows)!==count($indices))throw new \RuntimeException('Frozen NFT result count mismatch');$out=[];foreach($rows as $i=>$r)$out[]=$this->frozenRow($indices[$i],$r,$block);return $out; }
 private function frozenRow(string $index,array $r,string $block): FrozenStake
 { $p=new StakePosition($r[0]);if(array_slice($r[0],0,7)!==['0x'.str_repeat('0',40),'0x'.str_repeat('0',40),'0x'.str_repeat('0',40),'0','0','0','0']&&!in_array($p->fields['tokenType'],['2','3'],true))throw new \RuntimeException('Frozen position is not an NFT stake');return new FrozenStake($index,$p->fields,$r[1],$r[2],$r[3],$block); }
 public function freezeTime(string $freezeType,string $block='latest'): string
 { if(!in_array($freezeType,['1','2'],true))throw new \InvalidArgumentException('Withdrawal/transfer freeze type required');return $this->contract->call($this->client,'getFreezeTime(uint8)',[$freezeType],$block)[0]; }
 public function complete(string $sender,array $indices): TransactionDraft
 { if(!array_is_list($indices)||!$indices||count(array_unique($indices))!==count($indices))throw new \InvalidArgumentException('Unique nonempty NFT indices required');return $this->contract->draft($sender,'complete(uint256[])',[$indices]); }
 public function penaltyIndex(string $stakeId,string $block='latest'): string
 { return $this->contract->call($this->client,'getNFTStakePenaltyIndex(bytes32)',[$stakeId],$block)[0]; }
}
