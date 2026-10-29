<?php
declare(strict_types=1);
namespace Decimal;
final class ReferenceOperations
{
    public static function all(): array { return json_decode(file_get_contents(dirname(__DIR__).'/docs/reference-operations.json'),true,512,JSON_THROW_ON_ERROR); }
    public static function draft(string $operation,string $profile,string $target,string $sender,array $args=[],string $value='0',?string $signature=null): TransactionDraft
    {
        if($operation==='sendDEL')return new TransactionDraft($sender,$target,$value);
        if($operation==='burnDEL')return new TransactionDraft($sender,'0x'.str_repeat('0',40),$value);
        if($operation==='multiSendToken')throw new \InvalidArgumentException('Use sender-restricted multisend workflow');
        $family=preg_replace('/^Testnet(.*)20261029$/','$1',$profile);if(in_array($family,['ReserveToken','ReservelessToken'],true))$family='Token';
        foreach(self::all() as $spec)if($spec['operation']===$operation){
            if(!in_array($family,$spec['profiles'],true)||($operation==='mintTokenReserveless'&&$profile!=='TestnetReservelessToken20261029'))throw new \InvalidArgumentException('Operation profile mismatch');
            $names=$spec['methods'];if($profile==='TestnetReserveToken20261029'&&$operation==='updateTokenIdentity')$names=['updateIdentity'];
            $contract=Contract::profile($target,$profile);
            if($signature!==null){
                if(!in_array(strstr($signature,'(',true),$names,true))throw new \InvalidArgumentException('Signature outside operation');
                return $contract->draft($sender,$signature,$args,$value);
            }
            $abi=json_decode(file_get_contents(dirname(__DIR__).'/abi/'.$profile.'.json'),true);$matches=[];
            foreach($abi as $entry)if(($entry['type']??'')==='function'&&in_array($entry['name'],$names,true)){
                try{$matches[]=$contract->draft($sender,Contract::signature($entry),$args,$value);}catch(\InvalidArgumentException){}
            }
            if(count($matches)!==1)throw new \InvalidArgumentException('Operation absent or overloaded; provide exact signature');return $matches[0];
        }
        throw new \InvalidArgumentException('Unknown operation');
    }
}
