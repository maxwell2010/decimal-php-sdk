<?php
declare(strict_types=1);
namespace Decimal;
final class Mnemonic
{
    private static function words(): array { return file(dirname(__DIR__).'/resources/bip39-english.txt',FILE_IGNORE_NEW_LINES); }
    private static function normalize(string $s): string { return \Normalizer::normalize($s,\Normalizer::FORM_KD); }
    public static function generate(int $words=12): string
    {
        if(!in_array($words,[12,15,18,21,24],true)) throw new \InvalidArgumentException('Invalid word count');
        $entropy=random_bytes(intdiv($words*11*32,33*8)); $bits='';
        foreach(str_split($entropy) as $b) $bits.=str_pad(decbin(ord($b)),8,'0',STR_PAD_LEFT);
        $check='';foreach(str_split(hash('sha256',$entropy,true)) as $b) $check.=str_pad(decbin(ord($b)),8,'0',STR_PAD_LEFT);
        $bits.=substr($check,0,intdiv(strlen($bits),32));$list=self::words();$out=[];
        foreach(str_split($bits,11) as $part) $out[]=$list[bindec($part)];return implode(' ',$out);
    }
    public static function validate(#[\SensitiveParameter] string $phrase): bool
    {
        $words=explode(' ',self::normalize(trim($phrase))); if(!in_array(count($words),[12,15,18,21,24],true)) return false;
        $map=array_flip(self::words());$bits=''; foreach($words as $word) { if(!isset($map[$word])) return false;$bits.=str_pad(decbin($map[$word]),11,'0',STR_PAD_LEFT); }
        $length=intdiv(strlen($bits)*32,33);$bytes='';foreach(str_split(substr($bits,0,$length),8) as $b) $bytes.=chr(bindec($b));
        $hash='';foreach(str_split(hash('sha256',$bytes,true)) as $b) $hash.=str_pad(decbin(ord($b)),8,'0',STR_PAD_LEFT);
        return substr($bits,$length)===substr($hash,0,strlen($bits)-$length);
    }
    public static function wallet(#[\SensitiveParameter] string $phrase,int $index=0,#[\SensitiveParameter] string $passphrase='',?string $path=null): Wallet
    {
        if(!self::validate($phrase)||$index<0||$index>=2147483648) throw new \InvalidArgumentException('Invalid mnemonic or index');
        $seed=hash_pbkdf2('sha512',self::normalize(trim($phrase)),'mnemonic'.self::normalize($passphrase),2048,64,true);
        $path??="m/44'/60'/0'/0/".$index;
        if(!preg_match("~^m(?:/[0-9]+'?)*$~D",$path)) throw new \InvalidArgumentException('Invalid derivation path');
        $i=hash_hmac('sha512',$seed,'Bitcoin seed',true);$key=substr($i,0,32);$chain=substr($i,32);$n=gmp_init('fffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141',16);
        if(gmp_cmp(gmp_init(bin2hex($key),16),$n)>=0||gmp_cmp(gmp_init(bin2hex($key),16),0)===0) throw new \RuntimeException('Invalid master key');
        foreach(array_slice(explode('/',$path),1) as $part) {
            $hardened=str_ends_with($part,"'");$part=rtrim($part,"'");
            if(strlen($part)>10||(int)$part>=2147483648) throw new \InvalidArgumentException('Child index out of range');
            $index=(int)$part+($hardened?2147483648:0);
            $data=$hardened?"\0".$key:hex2bin((new \Elliptic\EC('secp256k1'))->keyFromPrivate(bin2hex($key),'hex')->getPublic(true,'hex'));
            $i=hash_hmac('sha512',$data.pack('N',$index),$chain,true);$left=gmp_init(bin2hex(substr($i,0,32)),16);
            $child=gmp_mod(gmp_add($left,gmp_init(bin2hex($key),16)),$n);
            if(gmp_cmp($left,$n)>=0||gmp_cmp($child,0)===0) throw new \RuntimeException('Invalid BIP32 child; choose next index');
            $key=hex2bin(str_pad(gmp_strval($child,16),64,'0',STR_PAD_LEFT));$chain=substr($i,32);
        }
        return new Wallet(bin2hex($key));
    }
    public static function addresses(#[\SensitiveParameter] string $phrase,int $start=0,int $count=10,#[\SensitiveParameter] string $passphrase=''): array
    {
        if($count<1||$count>1000) throw new \InvalidArgumentException('Invalid address count');
        $out=[];for($i=0;$i<$count;++$i) $out[]=self::wallet($phrase,$start+$i,$passphrase)->address;return $out;
    }
}
