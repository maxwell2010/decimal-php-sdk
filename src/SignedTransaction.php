<?php
declare(strict_types=1);
namespace Decimal;
final readonly class SignedTransaction
{
    public string $hash;
    public string $sender;
    public int $type;
    public string $unsignedHash;
    public ?string $createdContract;
    public function __construct(public string $raw,public int $chainId)
    {
        if($chainId<1||!preg_match('/^0x(?:[0-9a-fA-F]{2})+$/D',$raw))throw new \InvalidArgumentException('Invalid signed transaction');
        $prefix=strtolower(substr($raw,2,2));$typed=in_array($prefix,['01','02'],true);$this->type=$typed?hexdec($prefix):0;$rlp=new Rlp();
        $f=$rlp->decode($typed?'0x'.substr($raw,4):$raw);$expected=$typed?($prefix==='01'?11:12):9;
        if(!is_array($f)||count($f)!==$expected)throw new \InvalidArgumentException('Invalid transaction field count');
        $sigAt=$expected-3;$destAt=$typed?($prefix==='01'?4:5):3;$accessAt=$typed?$sigAt-1:null;
        foreach($f as $i=>$v) {
            if($i===$accessAt){
                if(!is_array($v))throw new \InvalidArgumentException('Invalid access list');
                foreach($v as $row){if(!is_array($row)||count($row)!==2||!is_string($row[0])||strlen($row[0])!==40||!is_array($row[1]))throw new \InvalidArgumentException('Invalid access entry');foreach($row[1] as $k)if(!is_string($k)||strlen($k)!==64)throw new \InvalidArgumentException('Invalid storage key');}
            } elseif(!is_string($v))throw new \InvalidArgumentException('Expected transaction bytes');
            elseif($i!==$destAt&&$i!==$destAt+2&&(strlen($v)>64||($v!==''&&str_starts_with($v,'00'))))throw new \InvalidArgumentException('Noncanonical transaction quantity');
        }
        if(!in_array(strlen($f[$destAt]),[0,40],true))throw new \InvalidArgumentException('Expected destination address or creation');
        $num=static fn($v)=>Units::decimal('0x'.(ltrim($v,'0')?:'0'));
        $v=$num($f[$sigAt]);$r=$num($f[$sigAt+1]);$s=$num($f[$sigAt+2]);
        $nonceValue=$num($f[$typed?1:0]);$gasValue=$num($f[$typed?($prefix==='01'?3:4):2]);
        if(bccomp($nonceValue,'18446744073709551615',0)>0||$gasValue==='0'||bccomp($gasValue,'18446744073709551615',0)>0) throw new \InvalidArgumentException('Invalid uint64 nonce or gas');
        if($prefix==='02'&&bccomp($num($f[2]),$num($f[3]),0)>0) throw new \InvalidArgumentException('Priority fee exceeds max fee');
        $order='115792089237316195423570985008687907852837564279074904382605163141518161494337';
        if($r==='0'||$s==='0'||bccomp($r,$order, 0)>=0||bccomp($s,bcdiv($order,'2',0), 0)>0)throw new \InvalidArgumentException('Invalid or high-s signature');
        if($typed){if($num($f[0])!==(string)$chainId||!in_array($v,['0','1'],true))throw new \InvalidArgumentException('Typed chain or recovery mismatch');$parity=(int)$v;}
        else{if(bccomp($v,'35', 0)<0||bcdiv(bcsub($v,'35', 0),'2',0)!==(string)$chainId)throw new \InvalidArgumentException('EIP-155 chain mismatch');$parity=(int)bcmod(bcsub($v,'35', 0),'2', 0);}
        $convert=function($v)use(&$convert){return is_array($v)?array_map($convert,$v):($v===''?'':'0x'.$v);};
        $unsigned=array_map($convert,array_slice($f,0,$sigAt));if(!$typed)$unsigned=[...$unsigned,Units::hex((string)$chainId),'',''];
        $digest='0x'.\kornrunner\Keccak::hash(hex2bin(($typed?$prefix:'').$rlp->encode($unsigned)),256);
        $this->unsignedHash=$digest;
        $signature='0x'.str_pad($f[$sigAt+1],64,'0',STR_PAD_LEFT).str_pad($f[$sigAt+2],64,'0',STR_PAD_LEFT).dechex(27+$parity);
        $this->sender=Wallet::recover($digest,$signature);
        $nonce=$f[$typed?1:0];
        $this->createdContract=$f[$destAt]===''?'0x'.substr(\kornrunner\Keccak::hash(hex2bin($rlp->encode([$this->sender,$nonce===''?'':'0x'.$nonce])),256),24):null;
        $this->hash='0x'.\kornrunner\Keccak::hash(hex2bin(substr($raw,2)),256);
    }
}
