<?php
declare(strict_types=1);
namespace Decimal;
/** EIP2612 data preparation for external signers; no private key or broadcast is required. */
final class PermitService
{
    public function __construct(private readonly Client $client,private readonly string $token) { TransactionDraft::address($token); }
    public function prepare(string $owner,string $spender,string $value,string $deadline): array
    {
        TransactionDraft::address($owner);TransactionDraft::address($spender);Units::uint($value);Units::uint($deadline);
        if($value==='0'||bccomp($deadline,(string)time(),0)<=0)throw new \InvalidArgumentException('Positive permit value and future deadline required');
        $erc=new Erc20Service($this->client,$this->token);$name=$erc->read('name');$nonce=$erc->read('nonce',[$owner]);$actual=$erc->read('DOMAIN_SEPARATOR');$version='1';
        try{$version=$erc->read('version');}catch(RpcException $error){
            $message=$error->getMessage();$missing="function selector was not recognized and there's no fallback function";
            if(!in_array($error->getCode(),[3,-32000],true)||!in_array($error->rpcData,[null,'0x'],true)||!in_array($message,['execution reverted','execution reverted: '.$missing,$missing],true))throw $error;
        }
        if(trim($name)==='')throw new \RuntimeException('Empty permit token name');
        $types=['Permit'=>array_map(static fn($f)=>['name'=>$f[0],'type'=>$f[1]],[['owner','address'],['spender','address'],['value','uint256'],['nonce','uint256'],['deadline','uint256']])];
        $domain=['name'=>$name,'version'=>$version,'chainId'=>(string)$this->client->network->chainId,'verifyingContract'=>$this->token];$message=compact('owner','spender','value','nonce','deadline');
        $preimage=TypedData::preimage($types,'Permit',$domain,$message);$separator='0x'.bin2hex(substr($preimage,2,32));
        if(strtolower($actual)!==$separator)throw new \RuntimeException('Permit domain mismatch');
        return ['types'=>$types,'primaryType'=>'Permit','domain'=>$domain,'message'=>$message,'domainSeparator'=>$separator,'digest'=>TypedData::hash($types,'Permit',$domain,$message)];
    }
    public static function verify(array $data,string $signature): array
    {
        if(!preg_match('/^0x[0-9a-fA-F]{130}$/D',$signature))throw new \InvalidArgumentException('Invalid permit signature');
        $v=hexdec(substr($signature,-2));if($v<2)$v+=27;if(!in_array($v,[27,28],true)||strcmp(strtolower(substr($signature,66,64)),'7fffffffffffffffffffffffffffffff5d576e7357a4501ddfe92f46681b20a0')>0)throw new \InvalidArgumentException('Noncanonical permit signature');
        $signature=substr($signature,0,-2).dechex($v);
        $canonical=['Permit'=>array_map(static fn($f)=>['name'=>$f[0],'type'=>$f[1]],[['owner','address'],['spender','address'],['value','uint256'],['nonce','uint256'],['deadline','uint256']])];
        if(($data['types']??null)!==$canonical)throw new \InvalidArgumentException('Noncanonical Permit schema');
        $digest=TypedData::hash($data['types'],$data['primaryType'],$data['domain'],$data['message']);
        if(($data['primaryType']??'')!=='Permit'||strtolower($digest)!==strtolower($data['digest']??'')||TransactionDraft::address(Wallet::recover($digest,$signature))!==TransactionDraft::address($data['message']['owner']))throw new \InvalidArgumentException('External permit signature mismatch');
        return ['signature'=>$signature,'v'=>(string)hexdec(substr($signature,-2)),'r'=>'0x'.substr($signature,2,64),'s'=>'0x'.substr($signature,66,64),'deadline'=>$data['message']['deadline']];
    }
}
