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
        $signers=self::signers($passwords);Units::uint($amount);$value=bcmul((string)count($signers),$amount,0);Units::uint($value);
        return $this->contract->draft($sender,'createChecksDEL(address[],uint256,uint256,uint256)',[$signers,$amount,$dueBlock,$nonce??$this->nonce($sender)],$value);
    }
    public function createToken(string $sender,#[\SensitiveParameter] array $passwords,string $amount,string $dueBlock,string $token,?string $nonce=null,?array $permit=null): array
    {
        $signers=self::signers($passwords);Units::uint($amount);$total=bcmul((string)count($signers),$amount,0);Units::uint($total);
        $args=[$signers,$amount,$dueBlock,$nonce??$this->nonce($sender),$token];
        if($permit!==null){
            $args=[...$args,$permit['deadline'],$permit['v'],$permit['r'],$permit['s']];
            $action=$this->contract->draft($sender,'createChecksTokenByPermit(address[],uint256,uint256,uint256,address,uint256,uint8,bytes32,bytes32)',$args);return ['approvals'=>[],'action'=>$action];
        }
        $action=$this->contract->draft($sender,'createChecksToken(address[],uint256,uint256,uint256,address)',$args);
        return Workflows::allowance($this->client,$token,$this->address,$total,$action);
    }
    public function redeem(string $redeemer,#[\SensitiveParameter] array $passwords,array $checks): TransactionDraft
    {
        if(!array_is_list($checks)||!$checks||count($checks)!==count($passwords)||count($checks)>500)throw new \InvalidArgumentException('Matching check/password lists required');
        $signatures=[];foreach($checks as $i=>$check)$signatures[]=self::passwordWallet($passwords[$i])->signHash(self::redeemDigest($check,$redeemer));
        return $this->contract->draft($redeemer,'redeemChecks(bytes[],bytes32[])',[$signatures,$checks]);
    }
    public function receipt(array $receipt): array { return Events::receipt('Checks',$receipt,$this->address); }
}
