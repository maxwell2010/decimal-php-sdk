<?php
declare(strict_types=1);
namespace Decimal;
final class TypedData
{
    private static function hashBytes(string $bytes): string { return hex2bin(\kornrunner\Keccak::hash($bytes,256)); }
    private static function dependencies(string $name,array $types,array &$seen): void
    {
        if(isset($seen[$name])) return;
        if(!isset($types[$name])) throw new \InvalidArgumentException('Unknown typed struct');
        $seen[$name]=true;
        foreach($types[$name] as $field) { $base=preg_replace('/\[.*$/','',$field['type']); if(isset($types[$base])) self::dependencies($base,$types,$seen); }
    }
    public static function encodeType(string $name,array $types): string
    {
        $seen=[];self::dependencies($name,$types,$seen);unset($seen[$name]);$names=array_keys($seen);sort($names,SORT_STRING);array_unshift($names,$name);$out='';
        foreach($names as $n) $out.=$n.'('.implode(',',array_map(static fn($f)=>$f['type'].' '.$f['name'],$types[$n])).')';return $out;
    }
    private static function field(string $type,mixed $value,array $types,int $depth): string
    {
        if($depth>64) throw new \InvalidArgumentException('Typed data nesting limit');
        if(preg_match('/^(.*)\[([0-9]*)\]$/D',$type,$m)) {
            if(!is_array($value)||!array_is_list($value)||($m[2]!==''&&count($value)!==(int)$m[2])) throw new \InvalidArgumentException('Invalid typed array');
            return self::hashBytes(implode('',array_map(static fn($v)=>self::field($m[1],$v,$types,$depth+1),$value)));
        }
        if(isset($types[$type])) return self::structHash($type,$value,$types,$depth+1);
        if($type==='string') return self::hashBytes($value);
        if($type==='bytes') { if(!preg_match('/^0x(?:[0-9a-fA-F]{2})*$/D',$value)) throw new \InvalidArgumentException('Invalid bytes');return self::hashBytes(hex2bin(substr($value,2))); }
        return hex2bin(substr((new AbiCodec())->encodeParameters([$type],[$value]),2));
    }
    private static function structHash(string $type,array $value,array $types,int $depth=0): string
    {
        $bytes=self::hashBytes(self::encodeType($type,$types));
        foreach($types[$type] as $field) { if(!array_key_exists($field['name'],$value)) throw new \InvalidArgumentException('Missing typed field');$bytes.=self::field($field['type'],$value[$field['name']],$types,$depth); }
        if(count($value)!==count($types[$type])) throw new \InvalidArgumentException('Unexpected typed field');
        return self::hashBytes($bytes);
    }
    public static function hash(array $types,string $primaryType,array $domain,array $message): string
    {
        if(!isset($types['EIP712Domain'])) {
            $fields=['name'=>'string','version'=>'string','chainId'=>'uint256','verifyingContract'=>'address','salt'=>'bytes32'];$types['EIP712Domain']=[];
            foreach($fields as $name=>$type) if(array_key_exists($name,$domain)) $types['EIP712Domain'][]=['name'=>$name,'type'=>$type];
        }
        return '0x'.bin2hex(self::hashBytes("\x19\x01".self::structHash('EIP712Domain',$domain,$types).self::structHash($primaryType,$message,$types)));
    }
    public static function permit(Wallet $wallet,string $token,int $chainId,string $name,string $version,string $spender,string $value,string $nonce,string $deadline): array
    {
        $types=['Permit'=>array_map(static fn($v)=>['name'=>$v[0],'type'=>$v[1]],[['owner','address'],['spender','address'],['value','uint256'],['nonce','uint256'],['deadline','uint256']])];
        $message=['owner'=>$wallet->address,'spender'=>$spender,'value'=>$value,'nonce'=>$nonce,'deadline'=>$deadline];
        $sig=$wallet->signTypedData($types,'Permit',['name'=>$name,'version'=>$version,'chainId'=>(string)$chainId,'verifyingContract'=>$token],$message);
        return ['signature'=>$sig,'v'=>(string)hexdec(substr($sig,-2)),'r'=>'0x'.substr($sig,2,64),'s'=>'0x'.substr($sig,66,64)];
    }
}
