<?php
declare(strict_types=1);
namespace Decimal;
/** Password checks match the pinned official JS EVM workflow, not Cosmos checks. */
final class ChecksService
{
    private Contract $contract;
    public function __construct(private readonly Client $client,public readonly string $address)
    { $this->contract=Contract::profile($address,'Checks'); }
    public static function passwordWallet(#[\SensitiveParameter] string $password): Wallet
    {
        if($password===''||!mb_check_encoding($password,'UTF-8'))throw new \InvalidArgumentException('Nonempty UTF-8 password required');
        return new Wallet(\kornrunner\Keccak::hash($password,256));
    }
    public static function redeemMessageHash(string $checkHash,string $redeemer): string
    {
        if(!preg_match('/^0x[0-9a-fA-F]{64}$/D',$checkHash))throw new \InvalidArgumentException('Expected bytes32 check hash');
        $address=TransactionDraft::address($redeemer);
        return '0x'.\kornrunner\Keccak::hash(hex2bin(substr($checkHash,2).substr($address,2)),256);
    }
    public static function redeemDigest(string $checkHash,string $redeemer): string
    { return '0x'.\kornrunner\Keccak::hash("\x19Ethereum Signed Message:\n32".hex2bin(substr(self::redeemMessageHash($checkHash,$redeemer),2)),256); }
    public function nonce(string $sender,string $block='latest'): string
    {
        TransactionDraft::address($sender);
        $raw=$this->client->call(['from'=>$sender,'to'=>$this->address,'data'=>$this->contract->encode('nonces()')],$block);
        return $this->contract->decode('nonces()',$raw)[0];
    }
    private static function signers(#[\SensitiveParameter] array $passwords): array
    {
        if(!array_is_list($passwords)||!$passwords||count($passwords)>500)throw new \InvalidArgumentException('Checks require 1..500 passwords');
        return array_map(static fn($p)=>self::passwordWallet($p)->address,$passwords);
    }
    public function createDel(string $sender,#[\SensitiveParameter] array $passwords,string $amount,string $dueBlock,?string $nonce=null): TransactionDraft
    {
        return $this->createDelForSigners($sender,self::signers($passwords),$amount,$dueBlock,$nonce);
    }
    public function createToken(string $sender,#[\SensitiveParameter] array $passwords,string $amount,string $dueBlock,string $token,?string $nonce=null,?array $permit=null): array
    {
        return $this->createTokenForSigners($sender,self::signers($passwords),$amount,$dueBlock,$token,$nonce,$permit,true);
    }
    public function redeem(string $redeemer,#[\SensitiveParameter] array $passwords,array $checks): TransactionDraft
    {
        if(!array_is_list($checks)||!$checks||count($checks)!==count($passwords)||count($checks)>500)throw new \InvalidArgumentException('Matching check/password lists required');
        $signatures=[];foreach($checks as $i=>$check)$signatures[]=self::passwordWallet($passwords[$i])->signHash(self::redeemDigest($check,$redeemer));
        return $this->redeemSignatures($redeemer,$signatures,$checks);
    }
    public function receipt(array $receipt): array { return Events::receipt('Checks',$receipt,$this->address); }
    /** Python/C# callers may provide signer addresses directly instead of passwords. */
    private static function addresses(array $signers): array
    {
        if(!array_is_list($signers)||!$signers||count($signers)>500)throw new \InvalidArgumentException('Checks require 1..500 signer addresses');
        return array_map(TransactionDraft::address(...),$signers);
    }
    public function createDelForSigners(string $sender,array $signers,string $amount,string $dueBlock,?string $nonce=null): TransactionDraft
    {
        $signers=self::addresses($signers);Units::uint($amount);$total=bcmul((string)count($signers),$amount,0);Units::uint($total);
        return $this->contract->draft($sender,'createChecksDEL(address[],uint256,uint256,uint256)',[$signers,$amount,$dueBlock,$nonce??$this->nonce($sender)],$total);
    }
    public function createTokenForSigners(string $sender,array $signers,string $amount,string $dueBlock,string $token,?string $nonce=null,?array $permit=null,bool $autoApprove=false,bool $resetExisting=false): array
    {
        $signers=self::addresses($signers);Units::uint($amount);$total=bcmul((string)count($signers),$amount,0);Units::uint($total);
        $args=[$signers,$amount,$dueBlock,$nonce??$this->nonce($sender),$token];
        if($permit!==null){
            foreach(['deadline','v','r','s'] as $key)if(!isset($permit[$key])||!is_string($permit[$key]))throw new \InvalidArgumentException('Complete string permit fields required');
            self::signature($permit['r'].$permit['s'], $permit['v']);
            $action=$this->contract->draft($sender,'createChecksTokenByPermit(address[],uint256,uint256,uint256,address,uint256,uint8,bytes32,bytes32)',[...$args,$permit['deadline'],$permit['v'],$permit['r'],$permit['s']]);
        }else $action=$this->contract->draft($sender,'createChecksToken(address[],uint256,uint256,uint256,address)',$args);
        return $autoApprove&&$permit===null?Workflows::allowance($this->client,$token,$this->address,$total,$action,$resetExisting):['approvals'=>[],'action'=>$action];
    }
    private static function signature(string $parts,string $v): void
    {
        if(!preg_match('/^0x[0-9a-fA-F]{64}0x[0-9a-fA-F]{64}$/D',$parts)||!in_array($v,['27','28'],true))throw new \InvalidArgumentException('Canonical signature required');
        $r=substr($parts,2,64);$s=substr($parts,68,64);$order='fffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141';$half='7fffffffffffffffffffffffffffffff5d576e7357a4501ddfe92f46681b20a0';
        if($r===str_repeat('0',64)||$s===str_repeat('0',64)||strcmp(strtolower($r),$order)>=0||strcmp(strtolower($s),$half)>0)throw new \InvalidArgumentException('Invalid or high-S signature');
    }
    public function redeemSignatures(string $redeemer,#[\SensitiveParameter] array $signatures,array $checks): TransactionDraft
    {
        if(!array_is_list($signatures)||!array_is_list($checks)||!$checks||count($checks)>500||count($signatures)!==count($checks))throw new \InvalidArgumentException('Matching check/signature lists required');
        foreach($signatures as $signature){
            if(!is_string($signature)||!preg_match('/^0x[0-9a-fA-F]{130}$/D',$signature))throw new \InvalidArgumentException('65-byte signature required');
            self::signature('0x'.substr($signature,2,64).'0x'.substr($signature,66,64),(string)hexdec(substr($signature,-2)));
        }
        return $this->contract->draft($redeemer,'redeemChecks(bytes[],bytes32[])',[$signatures,$checks]);
    }
    public function owner(string $block='latest'): string { return $this->contract->call($this->client,'owner()',[],$block)[0]; }
    public function implementation(string $block='latest'): string { return $this->contract->call($this->client,'getImpl()',[],$block)[0]; }
    public function createdChecks(array $receipt): array
    {
        $out=[];foreach($this->receipt($receipt) as $event)if($event['event']==='CreatedCheck')$out[]=$event['args'];return $out;
    }
    public function redeemedChecks(array $receipt): array
    {
        $out=[];foreach($this->receipt($receipt) as $event)if($event['event']==='RedeemCheck')$out[]=$event['args'];return $out;
    }
}
