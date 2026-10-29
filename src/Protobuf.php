<?php
declare(strict_types=1);
namespace Decimal;
final class Protobuf
{
    private array $schema;
    public function __construct() { $this->schema=json_decode(file_get_contents(dirname(__DIR__).'/resources/cosmos/schema.json'),true,512,JSON_THROW_ON_ERROR); }
    public static function varint(string $value): string
    {
        if(!preg_match('/^(0|[1-9][0-9]*)$/D',$value)||bccomp($value,'18446744073709551615', 0)>0)throw new \InvalidArgumentException('Invalid uint64');
        $out='';do{$part=(int)bcmod($value,'128', 0);$value=bcdiv($value,'128',0);$out.=chr($part|($value==='0'?0:128));}while($value!=='0');return $out;
    }
    private function scalar(array $f,mixed $value): array
    {
        $type=$f['type'];
        if(in_array($type,[3,4,5,8,13,14,17,18],true)) {
            if($type===8){if(!is_bool($value))throw new \InvalidArgumentException('Expected protobuf bool');$value=$value?'1':'0';}
            if(!is_string($value)||!preg_match('/^-?(0|[1-9][0-9]*)$/D',$value))throw new \InvalidArgumentException('Protobuf integers are decimal strings');
            $signed=in_array($type,[3,5,14,17,18],true);$bits=in_array($type,[5,13,14,17],true)?32:64;
            if(bccomp($value,$signed?bcsub('0',bcpow('2',(string)($bits-1), 0), 0):'0', 0)<0||bccomp($value,bcpow('2',(string)($bits-($signed?1:0)), 0), 0)>=0)throw new \InvalidArgumentException('Protobuf integer overflow');
            if(in_array($type,[17,18],true))$value=str_starts_with($value,'-')?bcsub(bcmul(substr($value,1),'2', 0),'1', 0):bcmul($value,'2', 0);
            elseif(str_starts_with($value,'-'))$value=bcadd(bcpow('2','64', 0),$value, 0);
            return [0,self::varint($value)];
        }
        if($type===11)return [2,$this->encode($f['typeName'],$value)];
        if($type===9){if(!is_string($value)||!mb_check_encoding($value,'UTF-8'))throw new \InvalidArgumentException('Invalid protobuf string');return [2,$value];}
        if($type===12){if(!is_string($value)||!preg_match('/^0x(?:[0-9a-fA-F]{2})*$/D',$value))throw new \InvalidArgumentException('Expected hex protobuf bytes');return [2,hex2bin(substr($value,2))];}
        throw new \InvalidArgumentException('Unsupported protobuf field type: '.$type);
    }
    public function encode(string $name,array $values): string
    {
        $spec=$this->schema[ltrim($name,'/')]??throw new \InvalidArgumentException('Unknown protobuf message');$out='';$known=[];$oneofs=[];
        foreach($spec['fields'] as $field){
            $key=$field['name'];$known[]=$key;if(!array_key_exists($key,$values))continue;
            if($field['oneof']!==null){if(isset($oneofs[$field['oneof']]))throw new \InvalidArgumentException('Conflicting oneof fields');$oneofs[$field['oneof']]=true;}
            $list=$field['repeated']?$values[$key]:[$values[$key]];if(!is_array($list)||!array_is_list($list))throw new \InvalidArgumentException('Expected repeated list');$packed='';
            foreach($list as $value){[$wire,$data]=$this->scalar($field,$value);
                if($field['packed']&&$wire===0){$packed.=$data;continue;}
                if(!$field['repeated']&&$field['oneof']===null&&(($wire===0&&$data==="\0")||($wire===2&&$data===''&&$field['type']!==11)))continue;
                $out.=self::varint((string)($field['number']*8+$wire)).($wire===2?self::varint((string)strlen($data)):'').$data;
            }
            if($packed!=='')$out.=self::varint((string)($field['number']*8+2)).self::varint((string)strlen($packed)).$packed;
        }
        if(array_diff(array_keys($values),$known))throw new \InvalidArgumentException('Unknown protobuf fields');return $out;
    }
    public function any(string $type,array $values): array
    {
        $allowed=json_decode(file_get_contents(dirname(__DIR__).'/resources/cosmos/inventory.json'),true);
        if(!in_array($type,$allowed,true))throw new \InvalidArgumentException('Unknown Decimal transaction type');
        return ['type_url'=>$type,'value'=>'0x'.bin2hex($this->encode($type,$values))];
    }
}
