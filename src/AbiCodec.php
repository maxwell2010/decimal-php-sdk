<?php
declare(strict_types=1);
namespace Decimal;
/** Strict Solidity ABI coder. Integers are decimal strings. */
final class AbiCodec
{
    public function encodeFunctionSignature(string $signature): string { return '0x'.substr(\kornrunner\Keccak::hash($signature,256),0,8); }
    public static function split(string $types): array
    {
        if ($types === '') return [];
        $depth=0; $start=0; $out=[];
        for($i=0;$i<strlen($types);++$i) {
            if($types[$i]==='(') ++$depth;
            if($types[$i]===')' && --$depth<0) throw new \InvalidArgumentException('Invalid ABI tuple');
            if($types[$i]===',' && $depth===0) { $out[]=substr($types,$start,$i-$start); $start=$i+1; }
        }
        if($depth!==0) throw new \InvalidArgumentException('Unbalanced ABI tuple');
        $out[]=substr($types,$start); return $out;
    }
    private function arrayType(string $t): ?array { return preg_match('/^(.*)\[([0-9]*)\]$/D',$t,$m) ? [$m[1],$m[2]===''?null:(int)$m[2]] : null; }
    private function tuple(string $t): ?array { return str_starts_with($t,'(') && str_ends_with($t,')') ? self::split(substr($t,1,-1)) : null; }
    private function dynamic(string $t): bool
    {
        if($a=$this->arrayType($t)) return $a[1]===null || $this->dynamic($a[0]);
        if(($types=$this->tuple($t))!==null) { foreach($types as $v) if($this->dynamic($v)) return true; return false; }
        return $t==='bytes'||$t==='string';
    }
    private function size(string $t): int
    {
        if($this->dynamic($t)) return 32;
        if($a=$this->arrayType($t)) return $a[1]*$this->size($a[0]);
        if(($types=$this->tuple($t))!==null) return array_sum(array_map($this->size(...),$types));
        return 32;
    }
    private function word(string $n): string { return str_pad(substr(Units::hex($n),2),64,'0',STR_PAD_LEFT); }
    private function bytes(string $v): string
    {
        if(!preg_match('/^0x(?:[0-9a-fA-F]{2})*$/D',$v)) throw new \InvalidArgumentException('Invalid hex bytes');
        return strtolower(substr($v,2));
    }
    public function encodeParameters(array $types,array $args): string { return '0x'.$this->sequence($types,$args); }
    private function sequence(array $types,array $args): string
    {
        if(!array_is_list($args)||count($types)!==count($args)) throw new \InvalidArgumentException('ABI argument count');
        $length=array_sum(array_map($this->size(...),$types)); $head='';$tail='';
        foreach($types as $i=>$t) {
            $v=$this->encode($t,$args[$i]);
            if($this->dynamic($t)) { $head.=$this->word((string)($length+strlen($tail)/2)); $tail.=$v; } else $head.=$v;
        }
        return $head.$tail;
    }
    private function encode(string $t,mixed $v): string
    {
        if($a=$this->arrayType($t)) {
            if(!is_array($v)||!array_is_list($v)||($a[1]!==null&&count($v)!==$a[1])) throw new \InvalidArgumentException('ABI array length');
            return ($a[1]===null?$this->word((string)count($v)):'').$this->sequence(array_fill(0,count($v),$a[0]),$v);
        }
        if(($types=$this->tuple($t))!==null) return $this->sequence($types,$v);
        if($t==='address') return str_pad(substr(TransactionDraft::address($v),2),64,'0',STR_PAD_LEFT);
        if($t==='bool') { if(!is_bool($v)) throw new \InvalidArgumentException('Expected bool'); return $this->word($v?'1':'0'); }
        if(preg_match('/^(u?int)([0-9]*)$/D',$t,$m)) {
            $bits=(int)($m[2]?:256);$signed=$m[1]==='int';
            if($bits<8||$bits>256||$bits%8!==0||!is_string($v)||!preg_match('/^-?(0|[1-9][0-9]*)$/D',$v)) throw new \InvalidArgumentException('Invalid ABI integer');
            $limit=bcpow('2',(string)($bits-($signed?1:0)), 0);
            if(bccomp($v,$signed?bcsub('0',$limit, 0):'0', 0)<0||bccomp($v,$limit, 0)>=0) throw new \InvalidArgumentException('ABI integer overflow');
            return $this->word(str_starts_with($v,'-')?bcadd(bcpow('2','256', 0),$v, 0):$v);
        }
        if($t==='string'||$t==='bytes') {
            $bytes=$t==='string'?bin2hex($v):$this->bytes($v);
            return $this->word((string)(strlen($bytes)/2)).str_pad($bytes,(int)(ceil(strlen($bytes)/64)*64),'0');
        }
        if(preg_match('/^bytes([0-9]+)$/D',$t,$m)&&($n=(int)$m[1])>=1&&$n<=32) {
            $bytes=$this->bytes($v);if(strlen($bytes)!==$n*2) throw new \InvalidArgumentException('Fixed bytes size');return str_pad($bytes,64,'0');
        }
        throw new \InvalidArgumentException('Unsupported ABI type: '.$t);
    }
    public function decodeParameters(array $types,string $data): array
    {
        $data=hex2bin($this->bytes($data));
        $result=$this->readSequence($types,$data,0);
        if($this->encodeParameters($types,$result)!=='0x'.bin2hex($data)) throw new \InvalidArgumentException('Noncanonical ABI payload');
        return $result;
    }
    private function slice(string $data,int $at,int $length): string
    {
        if($at<0||$length<0||$at>strlen($data)-$length) throw new \InvalidArgumentException('Truncated ABI payload');
        return substr($data,$at,$length);
    }
    private function number(string $data,int $at): string { return Units::decimal('0x'.(ltrim(bin2hex($this->slice($data,$at,32)),'0')?:'0')); }
    private function offset(string $data,int $at): int
    { $n=$this->number($data,$at); if(bccomp($n,(string)strlen($data), 0)>0) throw new \InvalidArgumentException('ABI offset out of bounds');return (int)$n; }
    private function readSequence(array $types,string $data,int $base): array
    {
        $out=[];$at=$base;
        foreach($types as $t) { $out[]=$this->read($t,$data,$this->dynamic($t)?$base+$this->offset($data,$at):$at);$at+=$this->size($t); }
        return $out;
    }
    private function read(string $t,string $data,int $at): mixed
    {
        if($a=$this->arrayType($t)) {
            $n=$a[1]; if($n===null) { $n=$this->offset($data,$at);$at+=32; }
            if($n>intdiv(strlen($data),32)) throw new \InvalidArgumentException('ABI array too long');
            return $this->readSequence(array_fill(0,$n,$a[0]),$data,$at);
        }
        if(($types=$this->tuple($t))!==null) return $this->readSequence($types,$data,$at);
        if($t==='string'||$t==='bytes') { $n=$this->offset($data,$at);$v=$this->slice($data,$at+32,$n);return $t==='string'?$v:'0x'.bin2hex($v); }
        $hex=bin2hex($this->slice($data,$at,32));
        if($t==='address') return '0x'.substr($hex,24);
        if($t==='bool') { $n=$this->number($data,$at);if($n!=='0'&&$n!=='1') throw new \InvalidArgumentException('Invalid ABI bool');return $n==='1'; }
        if(preg_match('/^(u?int)([0-9]*)$/D',$t,$m)) { $v=$this->number($data,$at);if($m[1]==='int'&&hexdec($hex[0])>=8) $v=bcsub($v,bcpow('2','256', 0), 0);return $v; }
        if(preg_match('/^bytes([0-9]+)$/D',$t,$m)) return '0x'.substr($hex,0,(int)$m[1]*2);
        throw new \InvalidArgumentException('Unsupported ABI type');
    }
}
