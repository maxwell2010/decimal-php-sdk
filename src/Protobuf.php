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
    private function scalar(array $f,mixed $value,int $depth=0): array
    {
        $type=$f['type'];
        if($type===1){if((!is_float($value)&&!is_int($value))||!is_finite((float)$value))throw new \InvalidArgumentException('Expected finite protobuf double');return [1,pack('e',(float)$value)];}
        if(in_array($type,[3,4,5,8,13,14,17,18],true)) {
            if($type===8){if(!is_bool($value))throw new \InvalidArgumentException('Expected protobuf bool');$value=$value?'1':'0';}
            if(!is_string($value)||!preg_match('/^-?(0|[1-9][0-9]*)$/D',$value))throw new \InvalidArgumentException('Protobuf integers are decimal strings');
            $signed=in_array($type,[3,5,14,17,18],true);$bits=in_array($type,[5,13,14,17],true)?32:64;
            if(bccomp($value,$signed?bcsub('0',bcpow('2',(string)($bits-1), 0), 0):'0', 0)<0||bccomp($value,bcpow('2',(string)($bits-($signed?1:0)), 0), 0)>=0)throw new \InvalidArgumentException('Protobuf integer overflow');
            if(in_array($type,[17,18],true))$value=str_starts_with($value,'-')?bcsub(bcmul(substr($value,1),'2', 0),'1', 0):bcmul($value,'2', 0);
            elseif(str_starts_with($value,'-'))$value=bcadd(bcpow('2','64', 0),$value, 0);
            return [0,self::varint($value)];
        }
        if($type===11)return [2,$this->encode($f['typeName'],$value,$depth+1)];
        if($type===9){if(is_string($value)&&strlen($value)>8*1024*1024)throw new \InvalidArgumentException('Protobuf resource limit');if(!is_string($value)||!mb_check_encoding($value,'UTF-8'))throw new \InvalidArgumentException('Invalid protobuf string');return [2,$value];}
        if($type===12){if(is_string($value)&&strlen($value)>2+16*1024*1024)throw new \InvalidArgumentException('Protobuf resource limit');if(!is_string($value)||!preg_match('/^0x(?:[0-9a-fA-F]{2})*$/D',$value))throw new \InvalidArgumentException('Expected hex protobuf bytes');return [2,hex2bin(substr($value,2))];}
        throw new \InvalidArgumentException('Unsupported protobuf field type: '.$type);
    }
    public function encode(string $name,array $values,int $depth=0): string
    {
        if($depth<0||$depth>64)throw new \InvalidArgumentException('Protobuf resource limit');
        $spec=$this->schema[ltrim($name,'/')]??throw new \InvalidArgumentException('Unknown protobuf message');$out='';$known=[];$oneofs=[];
        foreach($spec['fields'] as $field){
            $key=$field['name'];$known[]=$key;if(!array_key_exists($key,$values)){if($field['required']??false)throw new \InvalidArgumentException('Missing required protobuf field');continue;}
            if($field['oneof']!==null){if(isset($oneofs[$field['oneof']]))throw new \InvalidArgumentException('Conflicting oneof fields');$oneofs[$field['oneof']]=true;}
            $list=$field['repeated']?$values[$key]:[$values[$key]];if(!is_array($list)||!array_is_list($list))throw new \InvalidArgumentException('Expected repeated list');$packed='';
            foreach($list as $value){[$wire,$data]=$this->scalar($field,$value,$depth);
                if($field['packed']&&$wire===0){$packed.=$data;if(strlen($packed)>8*1024*1024)throw new \InvalidArgumentException('Protobuf resource limit');continue;}
                if(!$field['repeated']&&!($field['presence']??false)&&$field['oneof']===null&&(($wire===0&&$data==="\0")||($wire===2&&$data===''&&$field['type']!==11)))continue;
                $out.=self::varint((string)($field['number']*8+$wire)).($wire===2?self::varint((string)strlen($data)):'').$data;
                if(strlen($out)>8*1024*1024)throw new \InvalidArgumentException('Protobuf resource limit');
            }
            if($packed!=='')$out.=self::varint((string)($field['number']*8+2)).self::varint((string)strlen($packed)).$packed;
        }
        if(strlen($out)>8*1024*1024)throw new \InvalidArgumentException('Protobuf resource limit');
        if(array_diff(array_keys($values),$known))throw new \InvalidArgumentException('Unknown protobuf fields');return $out;
    }
    public function any(string $type,array $values): array
    {
        $allowed=json_decode(file_get_contents(dirname(__DIR__).'/resources/cosmos/inventory.json'),true);
        if(!in_array($type,$allowed,true))throw new \InvalidArgumentException('Unknown Decimal transaction type');
        return ['type_url'=>$type,'value'=>'0x'.bin2hex($this->encode($type,$values))];
    }
    private function readVarint(string $bytes,int &$at): string
    {
        $start=$at;$value='0';$factor='1';
        for($i=0;$i<10;++$i){
            if($at>=strlen($bytes))throw new \InvalidArgumentException('Truncated protobuf varint');
            $b=ord($bytes[$at++]);if($i===9&&$b>1)throw new \InvalidArgumentException('Protobuf varint overflow');
            $value=bcadd($value,bcmul((string)($b&127),$factor,0),0);
            if(($b&128)===0){if(self::varint($value)!==substr($bytes,$start,$at-$start))throw new \InvalidArgumentException('Noncanonical protobuf varint');return $value;}
            $factor=bcmul($factor,'128',0);
        }
        throw new \InvalidArgumentException('Protobuf varint overflow');
    }
    private function readScalar(array $field,int $wire,string $bytes,int &$at,int $depth): mixed
    {
        $type=$field['type'];
        if($type===1){if($wire!==1||$at>strlen($bytes)-8)throw new \InvalidArgumentException('Invalid protobuf double');$value=unpack('e',substr($bytes,$at,8))[1];$at+=8;if(!is_finite($value))throw new \InvalidArgumentException('Non-finite protobuf double');return $value;}
        if(in_array($type,[3,4,5,8,13,14,17,18],true)){
            if($wire!==0)throw new \InvalidArgumentException('Wrong protobuf scalar wire type');
            $value=$this->readVarint($bytes,$at);
            if($type===8){if(!in_array($value,['0','1'],true))throw new \InvalidArgumentException('Invalid protobuf bool');return $value==='1';}
            if(in_array($type,[17,18],true))$value=bcmod($value,'2',0)==='1'?bcsub('0',bcdiv(bcadd($value,'1',0),'2',0),0):bcdiv($value,'2',0);
            elseif(in_array($type,[3,5,14],true)&&bccomp($value,'9223372036854775808',0)>=0)$value=bcsub($value,'18446744073709551616',0);
            $this->scalar($field,$value);return $value;
        }
        if(!in_array($type,[9,11,12],true)||$wire!==2)throw new \InvalidArgumentException('Unsupported protobuf wire type');
        $size=$this->readVarint($bytes,$at);if(bccomp($size,(string)(strlen($bytes)-$at),0)>0)throw new \InvalidArgumentException('Truncated protobuf field');
        $value=substr($bytes,$at,(int)$size);$at+=(int)$size;
        if($type===11)return $this->decode($field['typeName'],$value,$depth+1);
        if($type===12)return '0x'.bin2hex($value);
        if(!mb_check_encoding($value,'UTF-8'))throw new \InvalidArgumentException('Invalid protobuf UTF-8');return $value;
    }
    /** Decode known fields strictly. Unknown fields/duplicates are rejected rather than silently discarded. */
    public function decode(string $name,string $bytes,int $depth=0): array
    {
        if($depth<0||$depth>64||strlen($bytes)>8*1024*1024)throw new \InvalidArgumentException('Protobuf resource limit');
        $spec=$this->schema[ltrim($name,'/')]??throw new \InvalidArgumentException('Unknown protobuf message');
        $byNumber=[];foreach($spec['fields'] as $f)$byNumber[$f['number']]=$f;
        $result=[];$oneofs=[];$at=0;
        while($at<strlen($bytes)){
            $tag=$this->readVarint($bytes,$at);if(bccomp($tag,'4294967295',0)>0)throw new \InvalidArgumentException('Invalid protobuf field tag');
            $number=intdiv((int)$tag,8);$wire=(int)$tag&7;$field=$byNumber[$number]??throw new \InvalidArgumentException('Unknown protobuf field');$key=$field['name'];
            if(!$field['repeated']&&array_key_exists($key,$result))throw new \InvalidArgumentException('Duplicate protobuf field');
            if($field['oneof']!==null){if(isset($oneofs[$field['oneof']]))throw new \InvalidArgumentException('Conflicting protobuf oneof');$oneofs[$field['oneof']]=true;}
            if($field['repeated']&&$wire===2&&in_array($field['type'],[3,4,5,8,13,14,17,18],true)){
                $length=$this->readVarint($bytes,$at);if(bccomp($length,(string)(strlen($bytes)-$at),0)>0)throw new \InvalidArgumentException('Truncated packed protobuf field');
                $packed=substr($bytes,$at,(int)$length);$at+=(int)$length;$pos=0;
                while($pos<strlen($packed))$result[$key][]=$this->readScalar($field,0,$packed,$pos,$depth);
            }else{$value=$this->readScalar($field,$wire,$bytes,$at,$depth);if($field['repeated'])$result[$key][]=$value;else $result[$key]=$value;}
        }
        foreach($spec['fields'] as $field)if(($field['required']??false)&&!array_key_exists($field['name'],$result))throw new \InvalidArgumentException('Missing required protobuf field');
        return $result;
    }
}
