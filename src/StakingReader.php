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
                $inner=[];foreach($component['components'] as $j=>$child)if(array_key_exists($j,$tuple[$i]))$inner[$child['name']]=$tuple[$i][$j];
                if(count($tuple[$i])===8 && array_keys($inner)===['validator','delegator','token','amount','tokenId','tokenType','holdTimestamp'])$inner['holdStartTime']=$tuple[$i][7];
                $result[$component['name']]=$inner;
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

    public function stake(string $validator,string $delegator,string $token,string $block='latest'): StakePosition
    { return new StakePosition($this->contract->call($this->client,'getStake(address,address,address)',[$validator,$delegator,$token],$block)[0]); }
    public function holdStake(string $validator,string $delegator,string $token,string $timestamp,string $block='latest'): StakePosition
    { Units::uint($timestamp);return new StakePosition($this->contract->call($this->client,'getHoldStake(address,address,address,uint256)',[$validator,$delegator,$token,$timestamp],$block)[0]); }
    /** Pin every constituent read to one block; missing positions stay distinct from failures. */
    public function snapshot(string $validator,string $delegator,string $token,array $timestamps,?string $block=null): array
    {
        $this->client->verifyChain();$block??=$this->client->rpc->call('eth_blockNumber');Units::decimal($block);
        if(!array_is_list($timestamps))throw new \InvalidArgumentException('Positional hold timestamps required');
        $regular=$this->stake($validator,$delegator,$token,$block);$holds=[];$missing=[];$held='0';
        foreach(array_unique($timestamps) as $timestamp){$row=$this->holdStake($validator,$delegator,$token,$timestamp,$block);if(!$row->exists())$missing[]=$timestamp;else{$holds[]=$row;$held=bcadd($held,$row->fields['amount'],0);}}
        return ['block'=>$block,'regular'=>$regular,'holds'=>$holds,'missing_hold_timestamps'=>$missing,'regular_amount_raw'=>$regular->fields['amount'],'held_amount_raw'=>$held,'total_amount_raw'=>bcadd($regular->fields['amount'],$held,0)];
    }
    public static function withdrawalReceipt(array $receipt,string $delegation,string $profile='Delegation'): array
    {
        $logs=[];
        foreach($receipt['logs']??[] as $log){
            if(strtolower($log['address']??'')!==TransactionDraft::address($delegation))continue;
            if(!in_array(strtolower($log['topics'][0]??''),['0xa7d58a1e25d2fea28ca76b72e922e949d2592d46feda6df44d1f9f801071c6ab','0x689537e7dce1fdf2a26f6db0ff663f74892eaaaac2413afbd0482333cecb0113'],true))continue;
            if(isset($log['transactionHash'])&&(!isset($receipt['transactionHash'])||strtolower($log['transactionHash'])!==strtolower($receipt['transactionHash'])))throw new \RuntimeException('Withdrawal transaction hash mismatch');
            if(($log['removed']??false)!==false)throw new \RuntimeException('Removed withdrawal log');
            foreach(['transactionHash','blockHash','blockNumber','transactionIndex'] as $key)if(isset($log[$key],$receipt[$key])&&strtolower($log[$key])!==strtolower($receipt[$key]))throw new \RuntimeException('Withdrawal receipt metadata mismatch');
            $logs[]=$log;
        }
        $receipt['logs']=$logs;$out=[];
        foreach(Events::receipt($profile,$receipt,$delegation) as $event){
            $args=$event['args'];$f=$args['frozenStake'];
            $out[]=['stakeId'=>$args['stakeId'],'frozenStake'=>new FrozenStake($args['stakeIndex'],(new StakePosition($f[0]))->fields,$f[1],$f[2],$f[3],$receipt['blockNumber']??'latest')];
        }
        return $out;
    }

    public function frozenPosition(string $index,string $block='latest'): FrozenStake
    {
        Units::uint($index);$tuple=$this->contract->call($this->client,'getFrozenStake(uint256)',[$index],$block)[0];
        return new FrozenStake($index,(new StakePosition($tuple[0]))->fields,$tuple[1],$tuple[2],$tuple[3],$block);
    }
    public function frozenPositions(array $indices,string $block='latest'): array
    {
        if(!array_is_list($indices)||count($indices)>128)throw new \InvalidArgumentException('Frozen batch must have at most 128 positional indices');
        $out=[];foreach($indices as $index)$out[]=$this->frozenPosition($index,$block);return $out;
    }
    public function applyPenalties(string $sender,array $stakeIds): TransactionDraft
    { if(!$stakeIds||!array_is_list($stakeIds))throw new \InvalidArgumentException('Stake IDs required');return $this->contract->draft($sender,'applyPenaltiesToStakes(bytes32[])',[$stakeIds]); }

    public function readWithdrawalReceipt(string $hash,string $profile='Delegation'): array
    {
        if(!preg_match('/^0x[0-9a-fA-F]{64}$/D',$hash))throw new \InvalidArgumentException('Expected transaction hash');
        $receipt=$this->client->receipt($hash);
        if($receipt===null)throw new \RuntimeException('Receipt not available');
        if(strtolower($receipt['transactionHash']??'')!==strtolower($hash))throw new \RuntimeException('Receipt transaction hash mismatch');
        return self::withdrawalReceipt($receipt,$this->contract->address,$profile);
    }
}
