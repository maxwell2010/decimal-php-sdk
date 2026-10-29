<?php
declare(strict_types=1);
namespace Decimal;
/** Caller-reviewed, sender-restricted ERC20 deployment; never generic Multicall. */
final class RestrictedMultisend
{
    private Contract $contract;
    public function __construct(private readonly Client $client,public readonly string $address,private readonly int $chainId,private readonly string $runtimeHash,private readonly ?string $implementation=null,private readonly ?string $implementationRuntimeHash=null)
    {
        if($chainId!==$client->network->chainId||TransactionDraft::address($address)==='0x'.str_repeat('0',40)||($implementation===null)!==($implementationRuntimeHash===null))throw new \InvalidArgumentException('Invalid or cross-network restricted multisend evidence');
        $base=[['type'=>'address'],['type'=>'address[]'],['type'=>'uint256[]'],['type'=>'bytes']];
        $this->contract=new Contract($address,[['type'=>'function','name'=>'sendToken','stateMutability'=>'nonpayable','inputs'=>$base,'outputs'=>[]],['type'=>'function','name'=>'sendTokenWithPermit','stateMutability'=>'nonpayable','inputs'=>[...$base,['type'=>'uint256'],['type'=>'uint8'],['type'=>'bytes32'],['type'=>'bytes32']],'outputs'=>[]]]);
    }
    public function verify(): string
    {
        $this->client->verifyChain();$block=$this->client->rpc->call('eth_blockNumber');Units::decimal($block);
        Deployment::verify($this->client,$this->address,$this->runtimeHash,$this->implementation,$block);
        if($this->implementation===null&&Deployment::implementation($this->client,$this->address,$block)!==null)throw new \RuntimeException('Proxy multisend requires trusted implementation evidence');
        if($this->implementation!==null)Deployment::verify($this->client,$this->implementation,$this->implementationRuntimeHash,null,$block);
        return $block;
    }
    private function batch(string $owner,string $token,array $recipients,string $memo): array
    {
        $owner=TransactionDraft::address($owner);$token=TransactionDraft::address($token);$zero='0x'.str_repeat('0',40);
        if($owner===$zero||$token===$zero||!array_is_list($recipients)||count($recipients)<1||count($recipients)>500||strlen($memo)>256||!mb_check_encoding($memo,'UTF-8'))throw new \InvalidArgumentException('Invalid restricted multisend batch');
        $addresses=[];$amounts=[];$total='0';
        foreach($recipients as $r){
            $target=TransactionDraft::address($r['address']);$amount=Units::uint($r['amount']);
            if(in_array($target,[$zero,$owner,TransactionDraft::address($this->address)],true)||$amount==='0')throw new \InvalidArgumentException('Invalid restricted multisend recipient');
            $addresses[]=$target;$amounts[]=$amount;$total=bcadd($total,$amount,0);Units::uint($total);
        }
        return [$addresses,$amounts,$total];
    }
    public function plan(string $owner,string $token,array $recipients,string $memo='',bool $resetExisting=false): array
    {
        [$addresses,$amounts,$total]=$this->batch($owner,$token,$recipients,$memo);$block=$this->verify();
        $erc20=Contract::profile($token,'Token');
        if(bccomp($erc20->call($this->client,'balanceOf(address)',[$owner],$block)[0],$total,0)<0)throw new \RuntimeException('Insufficient token balance');
        $action=$this->contract->draft($owner,'sendToken(address,address[],uint256[],bytes)',[$token,$addresses,$amounts,'0x'.bin2hex($memo)]);
        return Workflows::allowance($this->client,$token,$this->address,$total,$action,$resetExisting);
    }
    public function withPermit(Wallet $wallet,string $token,array $recipients,string $name,string $version,string $deadline,string $memo=''): array
    {
        [$addresses,$amounts,$total]=$this->batch($wallet->address,$token,$recipients,$memo);$block=$this->verify();
        if(bccomp(Contract::profile($token,'Token')->call($this->client,'balanceOf(address)',[$wallet->address],$block)[0],$total,0)<0)throw new \RuntimeException('Insufficient token balance');
        $permit=TypedData::checkedPermit($this->client,$wallet,$token,$name,$version,$this->address,$total,$deadline);
        $action=$this->contract->draft($wallet->address,'sendTokenWithPermit(address,address[],uint256[],bytes,uint256,uint8,bytes32,bytes32)',[$token,$addresses,$amounts,'0x'.bin2hex($memo),$deadline,$permit['v'],$permit['r'],$permit['s']]);
        return ['approvals'=>[],'action'=>$action,'permit'=>$permit];
    }
}
