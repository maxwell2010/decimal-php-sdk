<?php
declare(strict_types=1);
namespace Decimal;
/** Canonical RLP, preserving leading zeros in byte strings. Inputs are hex strings or lists. */
final class Rlp
{
    public function encode(array|string $value): string
    {
        if(is_array($value)) {
            if(!array_is_list($value)) throw new \InvalidArgumentException('Expected RLP list');
            $bytes='';foreach($value as $v)$bytes.=$this->encode($v);return $this->prefix(strlen($bytes)/2,192).$bytes;
        }
        if($value==='') $hex='';else {
            if(!preg_match('/^0x[0-9a-fA-F]*$/D',$value)) throw new \InvalidArgumentException('Expected hex RLP bytes');
            $hex=strtolower(substr($value,2));if(strlen($hex)%2)$hex='0'.$hex;
        }
        $length=intdiv(strlen($hex),2);if($length===1&&hexdec($hex)<128)return $hex;
        return $this->prefix($length,128).$hex;
    }
    private function prefix(int|float $length,int $offset): string
    {
        $length=(int)$length;if($length<56)return dechex($offset+$length);
        $hex=dechex($length);if(strlen($hex)%2)$hex='0'.$hex;return dechex($offset+55+strlen($hex)/2).$hex;
    }
    public function decode(string $raw): array|string
    {
        if(!preg_match('/^0x(?:[0-9a-fA-F]{2})+$/D',$raw))throw new \InvalidArgumentException('Invalid RLP bytes');
        $data=hex2bin(substr($raw,2));$at=0;$out=$this->read($data,$at,0);
        if($at!==strlen($data))throw new \InvalidArgumentException('Trailing RLP bytes');return $out;
    }
    private function take(string $s,int &$at,int $n): string { if($n<0||$at>strlen($s)-$n)throw new \InvalidArgumentException('Truncated RLP');$v=substr($s,$at,$n);$at+=$n;return $v; }
    private function read(string $s,int &$at,int $depth): array|string
    {
        if($depth>64)throw new \InvalidArgumentException('RLP nesting limit');
        $first=ord($this->take($s,$at,1));if($first<128)return bin2hex(chr($first));
        $list=$first>=192;$offset=$list?192:128;$size=$first-$offset;
        if($size>55) {
            $lengthBytes=$this->take($s,$at,$size-55);
            if(ord($lengthBytes[0])===0||strlen($lengthBytes)>4)throw new \InvalidArgumentException('Noncanonical RLP length');
            $size=(int)hexdec(bin2hex($lengthBytes));if($size<56)throw new \InvalidArgumentException('Noncanonical RLP size');
        }
        $bytes=$this->take($s,$at,$size);
        if(!$list) { if($size===1&&ord($bytes[0])<128)throw new \InvalidArgumentException('Noncanonical RLP string');return bin2hex($bytes); }
        $out=[];$pos=0;while($pos<strlen($bytes))$out[]=$this->read($bytes,$pos,$depth+1);return $out;
    }
}
