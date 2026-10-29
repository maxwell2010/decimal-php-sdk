<?php
declare(strict_types=1);
namespace Decimal;
final class Address
{
    public static function checksum(string $address): string
    {
        $hex=substr(TransactionDraft::address($address),2);$hash=\kornrunner\Keccak::hash($hex,256);$out='0x';
        for($i=0;$i<40;++$i)$out.=hexdec($hash[$i])>=8?strtoupper($hex[$i]):$hex[$i];return $out;
    }
    private static function polymod(array $values): int
    {
        $chk=1;$gen=[0x3b6a57b2,0x26508e6d,0x1ea119fa,0x3d4233dd,0x2a1462b3];
        foreach($values as $v){$top=$chk>>25;$chk=(($chk&0x1ffffff)<<5)^$v;for($i=0;$i<5;++$i)if(($top>>$i)&1)$chk^=$gen[$i];}return $chk;
    }
    private static function expand(string $hrp): array
    { $high=[];$low=[];foreach(str_split($hrp) as $c){$high[]=ord($c)>>5;$low[]=ord($c)&31;}return [...$high,0,...$low]; }
    private static function convert(array $values,int $from,int $to,bool $pad): array
    {
        $acc=0;$bits=0;$out=[];$mask=(1<<$to)-1;
        foreach($values as $v){$acc=(($acc<<$from)|$v)&((1<<($from+$to-1))-1);$bits+=$from;while($bits>=$to){$bits-=$to;$out[]=($acc>>$bits)&$mask;}}
        if($pad&&$bits)$out[]=($acc<<($to-$bits))&$mask;
        elseif(!$pad&&($bits>=$from||(($acc<<($to-$bits))&$mask)!==0))throw new \InvalidArgumentException('Invalid Bech32 padding');return $out;
    }
    public static function bech32(string $evmAddress,string $prefix='d0'): string
    {
        if(!preg_match('/^[a-z0-9]{1,40}$/D',$prefix))throw new \InvalidArgumentException('Invalid Bech32 prefix');
        $data=self::convert(array_values(unpack('C*',hex2bin(substr(TransactionDraft::address($evmAddress),2)))),8,5,true);
        $mod=self::polymod([...self::expand($prefix),...$data,0,0,0,0,0,0])^1;$chars='qpzry9x8gf2tvdw0s3jn54khce6mua7l';$out=$prefix.'1';foreach($data as $v)$out.=$chars[$v];for($i=0;$i<6;++$i)$out.=$chars[($mod>>(5*(5-$i)))&31];return $out;
    }
    public static function fromBech32(string $address,string $prefix='d0'): string
    {
        if(strlen($address)>90||$address!==strtolower($address))throw new \InvalidArgumentException('Invalid Bech32 case/length');
        $pos=strrpos($address,'1');if($pos===false||substr($address,0,$pos)!==$prefix)throw new \InvalidArgumentException('Bech32 prefix mismatch');
        $map=array_flip(str_split('qpzry9x8gf2tvdw0s3jn54khce6mua7l'));$values=[];foreach(str_split(substr($address,$pos+1)) as $c){if(!isset($map[$c]))throw new \InvalidArgumentException('Invalid Bech32 character');$values[]=$map[$c];}
        if(count($values)<6||self::polymod([...self::expand($prefix),...$values])!==1)throw new \InvalidArgumentException('Invalid Bech32 checksum');
        $bytes=self::convert(array_slice($values,0,-6),5,8,false);if(count($bytes)!==20)throw new \InvalidArgumentException('Expected 20-byte address');return '0x'.bin2hex(pack('C*',...$bytes));
    }
}
