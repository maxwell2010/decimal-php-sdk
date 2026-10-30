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
  return array_combine(array_column($entry['outputs'][0]['components'],'name'),$tuple);
 }
 public function stakeId(string $nft,string $id,string $delegator,string $validator,string $holdTimestamp='0',string $block='latest'): string
 { return $this->contract->call($this->client,'getNFTStakeId(address,uint256,address,address,uint256)',[$nft,$id,$delegator,$validator,$holdTimestamp],$block)[0]; }
 public function penaltyIndex(string $stakeId,string $block='latest'): string
 { return $this->contract->call($this->client,'getNFTStakePenaltyIndex(bytes32)',[$stakeId],$block)[0]; }
}
