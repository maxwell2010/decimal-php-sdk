<?php
declare(strict_types=1);
namespace Decimal;
/** Sorted Safe mixed signatures with explicit EIP-1271 interface selection. */
final class SafeSignatures
{
    private static function word(string $n): string { return str_pad(substr(Units::hex($n),2),64,'0',STR_PAD_LEFT); }
    public static function validateEoa(string $signature,bool $personal=false): string
    {
        if(!preg_match('/^0x[0-9a-fA-F]{130}$/D',$signature))throw new \InvalidArgumentException('Expected 65-byte EOA signature');
        $v=hexdec(substr($signature,-2));if($personal&&in_array($v,[31,32],true))$v-=4;
        if(!in_array($v,[0,1,27,28],true))throw new \InvalidArgumentException('Invalid EOA recovery ID');if($v<27)$v+=27;
        $r=gmp_init(substr($signature,2,64),16);$s=gmp_init(substr($signature,66,64),16);$n=gmp_init('fffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141',16);
        if(gmp_cmp($r,0)<=0||gmp_cmp($r,$n)>=0||gmp_cmp($s,0)<=0||gmp_cmp($s,gmp_div_q($n,2))>0)throw new \InvalidArgumentException('Invalid or high-s EOA signature');
        return substr($signature,0,-2).dechex($v);
    }
    public static function pack(Client $client,SafePolicy $policy,SafeTransaction $tx,array $signatures,string $executor): string
    {
        TransactionDraft::address($executor);
        if($client->network->chainId!==$policy->chainId||$tx->chainId!==$policy->chainId||strtolower($tx->safe)!==strtolower($policy->safe)||$tx->message['nonce']!==$policy->nonce)throw new \InvalidArgumentException('Safe signature snapshot mismatch');
        $rows=[];$approved='0';$hash=$tx->hash();$codec=new AbiCodec();
        foreach($signatures as $row){
            $owner=TransactionDraft::address($row['signer']);$kind=$row['kind'];$sig=$row['signature']??'0x';
            if(!isset($policy->owners[$owner])||isset($rows[$owner]))throw new \InvalidArgumentException('Unknown or duplicate Safe signer');
            if(!is_string($sig)||!preg_match('/^0x(?:[0-9a-fA-F]{2})*$/D',$sig))throw new \InvalidArgumentException('Invalid Safe signature bytes');
            $static='';$tail=null;
            if($kind==='typed'||$kind==='personal'){
                $sig=self::validateEoa($sig,$kind==='personal');
                $v=hexdec(substr($sig,-2));if($kind==='personal'&&in_array($v,[31,32],true))$v-=4;
                if(!in_array($v,[0,1,27,28],true))throw new \InvalidArgumentException('Invalid Safe signature recovery byte');if($v<27)$v+=27;
                $digest=$kind==='personal'?'0x'.\kornrunner\Keccak::hash("\x19Ethereum Signed Message:\n32".hex2bin(substr($hash,2)),256):$hash;
                if(Wallet::recover($digest,substr($sig,0,-2).dechex($v))!==$owner)throw new \InvalidArgumentException('Safe signature owner/hash mismatch');
                $static=substr($sig,2,128).dechex($v+($kind==='personal'?4:0));
            }elseif($kind==='contract'){
                if($sig==='0x')throw new \InvalidArgumentException('Empty contract signature');
                $variant=$row['interface']??null;
                if(!in_array($variant,['bytes32','bytes'],true))throw new \InvalidArgumentException('Explicit EIP-1271 interface required');
                $preimage=TypedData::preimage(SafeTransaction::types(),'SafeTx',['chainId'=>(string)$tx->chainId,'verifyingContract'=>$tx->safe],$tx->message);
                $signature=$variant==='bytes32'?'isValidSignature(bytes32,bytes)':'isValidSignature(bytes,bytes)';
                $args=[$variant==='bytes32'?$hash:'0x'.bin2hex($preimage),$sig];$types=$variant==='bytes32'?['bytes32','bytes']:['bytes','bytes'];
                $data=$codec->encodeFunctionSignature($signature).substr($codec->encodeParameters($types,$args),2);
                $result=$codec->decodeParameters(['bytes4'],$client->call(['to'=>$owner,'data'=>$data],$policy->block))[0];
                if($result!==($variant==='bytes32'?'0x1626ba7e':'0x20c13b0b'))throw new \RuntimeException('Contract signature rejected');$tail=substr($sig,2);
            }elseif($kind==='prevalidated'){
                if($sig!=='0x')throw new \InvalidArgumentException('Prevalidated signature has no payload');
                if(strtolower($executor)!==$owner){
                    $contract=Contract::profile($policy->safe,'TestnetSafe20261029');$value=$contract->call($client,'approvedHashes(address,bytes32)',[$owner,$hash],$policy->block)[0];
                    if($value==='0')throw new \RuntimeException('Safe hash not approved by owner');
                }
                $static=str_pad(substr($owner,2),64,'0',STR_PAD_LEFT).str_repeat('0',64).'01';
            }else throw new \InvalidArgumentException('Unknown Safe signature kind');
            $rows[$owner]=['static'=>$static,'tail'=>$tail];$approved=bcadd($approved,$policy->owners[$owner],0);
        }
        if(!$rows||bccomp($approved,$policy->threshold,0)<0)throw new \InvalidArgumentException('Insufficient Safe signature weight');ksort($rows,SORT_STRING);$head='';$tails='';
        foreach($rows as $owner=>$row){
            if($row['tail']===null){$head.=$row['static'];continue;}
            $head.=str_pad(substr($owner,2),64,'0',STR_PAD_LEFT).self::word((string)(65*count($rows)+intdiv(strlen($tails),2))).'00';
            $tails.=self::word((string)intdiv(strlen($row['tail']),2)).$row['tail'];
        }
        return '0x'.$head.$tails;
    }
    public static function draft(Client $client,SafePolicy $policy,SafeTransaction $tx,string $executor,array $signatures,?array $delegatecallTarget=null): TransactionDraft
    {
        if($tx->message['operation']==='1'){
            if($delegatecallTarget===null||TransactionDraft::address($delegatecallTarget['address'])!==TransactionDraft::address($tx->message['to'])||$tx->message['value']!=='0')throw new \InvalidArgumentException('Reviewed delegatecall target and zero value required');
            $actualImplementation=Deployment::implementation($client,$tx->message['to'],$policy->block);
            if($actualImplementation!==null&&!isset($delegatecallTarget['implementation']))throw new \InvalidArgumentException('Proxy delegatecall target requires reviewed implementation evidence');
            Deployment::verify($client,$tx->message['to'],$delegatecallTarget['runtimeHash'],$delegatecallTarget['implementation']??null,$policy->block);
            if(isset($delegatecallTarget['implementation'])){
                if(!isset($delegatecallTarget['implementationRuntimeHash']))throw new \InvalidArgumentException('Delegatecall implementation runtime hash required');
                Deployment::verify($client,$delegatecallTarget['implementation'],$delegatecallTarget['implementationRuntimeHash'],null,$policy->block);
            }
        }
        $policy->verifyHash($client,$tx);return $tx->draft($executor,self::pack($client,$policy,$tx,$signatures,$executor));
    }
}
