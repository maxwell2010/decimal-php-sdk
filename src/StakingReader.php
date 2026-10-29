<?php
declare(strict_types=1);
namespace Decimal;
final class StakingReader
{
    public function __construct(private readonly Client $client,private readonly Contract $contract) {}
    public function frozen(string $index,string $wallet,string $block): FrozenStake
    {
        Units::uint($index);Units::decimal($block);$wallet=TransactionDraft::address($wallet);
        $entry=$this->contract->function('getFrozenStake(uint256)');
        $tuple=$this->contract->call($this->client,'getFrozenStake(uint256)',[$index],$block)[0];
        $components=$entry['outputs'][0]['components'];$result=[];
        foreach ($components as $i=>$component) {
            if($component['type']==='tuple') {
                $inner=[];foreach($component['components'] as $j=>$child)$inner[$child['name']]=$tuple[$i][$j];$result[$component['name']]=$inner;
            } else $result[$component['name']]=$tuple[$i];
        }
        if(!isset($result['stake']['delegator'],$result['stake']['amount'],$result['freezeStatus'],$result['freezeType'],$result['unfreezeTimestamp']))throw new \RuntimeException('Unsupported frozen stake ABI');
        $row=new FrozenStake($index,$result['stake'],$result['freezeStatus'],$result['freezeType'],$result['unfreezeTimestamp'],$block);
        if($row->active()&&TransactionDraft::address($row->stake['delegator'])!==$wallet)throw new \RuntimeException('Frozen stake belongs to another wallet');return $row;
    }
    public function withdrawals(string $wallet,array $indices,string $block): array
    {
        if(count($indices)>500)throw new \InvalidArgumentException('Withdrawal page limit exceeded');$out=[];
        foreach(array_unique($indices) as $i){$row=$this->frozen($i,$wallet,$block);if($row->active())$out[]=$row;}return $out;
    }
    public function completeChecked(string $sender,array $indices,string $block): TransactionDraft
    {
        if(!$indices||count(array_unique($indices))!==count($indices))throw new \InvalidArgumentException('Unique withdrawal indices required');
        $rows=$this->withdrawals($sender,$indices,$block);
        if(count($rows)!==count($indices))throw new \RuntimeException('Withdrawal inactive or already completed');
        $header=$this->client->rpc->call('eth_getBlockByNumber',[$block,false]);$timestamp=Units::decimal($header['timestamp']??'');
        foreach($rows as $row)if(!$row->matured($timestamp))throw new \RuntimeException('Withdrawal is not yet mature');
        return $this->contract->draft($sender,'complete(uint256[])',[$indices]);
    }
    public function stakes(array $stakeIds,string $block): array
    { Units::decimal($block);return $this->contract->call($this->client,'getStakes(bytes32[])',[$stakeIds],$block)[0]; }
}
