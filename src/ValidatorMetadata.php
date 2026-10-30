<?php
declare(strict_types=1);
namespace Decimal;
/** Python ValidatorMetadata wire JSON with exact commission strings. */
final readonly class ValidatorMetadata
{
    public function __construct(public string $operatorAddress,public string $rewardAddress,public string $consensusPubkey,public array $description,public string $commission) { $this->toJson(); }
    public function toJson(): string
    {
        if(TransactionDraft::address($this->operatorAddress)==='0x'.str_repeat('0',40)||TransactionDraft::address($this->rewardAddress)==='0x'.str_repeat('0',40))throw new \InvalidArgumentException('Nonzero validator metadata addresses required');
        $nonempty=static fn(string $s):bool=>mb_check_encoding($s,'UTF-8')&&!preg_match('/^[\p{Z}\s]*$/u',$s);
        if(!$nonempty($this->consensusPubkey))throw new \InvalidArgumentException('Consensus pubkey required');
        if(array_diff(array_keys($this->description),['moniker','identity','website','security_contact','details']))throw new \InvalidArgumentException('Unknown validator description field');
        $description=[];foreach(['moniker','identity','website','security_contact','details'] as $key){$value=$this->description[$key]??'';if(!is_string($value)||!mb_check_encoding($value,'UTF-8'))throw new \InvalidArgumentException('String description fields required');$description[$key]=$value;}
        if(!$nonempty($description['moniker']))throw new \InvalidArgumentException('Validator moniker required');
        if(!mb_check_encoding($this->commission,'UTF-8'))throw new \InvalidArgumentException('UTF-8 commission required');
        $commission=str_replace('_','',preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u','',$this->commission));
        if(!preg_match('/^([+-]?)([0-9]*)(?:\.([0-9]*))?(?:[eE]([+-]?[0-9]+))?$/D',$commission,$m)||($m[2].($m[3]??''))==='')throw new \InvalidArgumentException('Finite exact commission required');
        $digits=$m[2].($m[3]??'');$leading=strspn($digits,'0');$significant=substr($digits,$leading);
        if($significant!==''){
            $position=strlen($m[2])+(int)($m[4]??'0')-$leading;
            if($m[1]==='-'||$position>3||($position===3&&strcmp(str_pad(rtrim($significant,'0'),3,'0'),'100')>0))throw new \InvalidArgumentException('Commission must be between 0 and 100');
        }
        return json_encode(['operator_address'=>$this->operatorAddress,'reward_address'=>$this->rewardAddress,'consensus_pubkey'=>$this->consensusPubkey,'description'=>$description,'commission'=>$this->commission],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }
}
