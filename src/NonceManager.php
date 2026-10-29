<?php
declare(strict_types=1);
namespace Decimal;

/** Explicit cross-process reservations. Never automatically releases a reserved nonce. */
final readonly class NonceManager
{
    public function __construct(private string $path)
    {
        if($path==='' || !is_dir(dirname($path))) throw new \InvalidArgumentException('Nonce store parent directory must exist');
    }
    public function reserve(int $chainId,string $address,string $pendingNonce): string
    {
        if($chainId<1) throw new \InvalidArgumentException('Invalid chain ID');
        $key=$chainId.':'.TransactionDraft::address($address);
        Units::uint($pendingNonce);
        if(bccomp($pendingNonce,'18446744073709551615',0)>0) throw new \InvalidArgumentException('Nonce exceeds uint64');
        $lock=@fopen($this->path.'.lock','c+b');
        if($lock===false) throw new \RuntimeException('Cannot open nonce reservation lock');
        $temporary=null;
        try {
            if(!flock($lock,LOCK_EX)) throw new \RuntimeException('Cannot lock nonce reservation store');
            $state=[];
            if(file_exists($this->path)) {
                $data=@fopen($this->path,'rb');
                if($data===false) throw new \RuntimeException('Cannot read nonce reservation store');
                try { $raw=stream_get_contents($data,1024*1024+1); } finally { fclose($data); }
                if($raw===false || $raw==='' || strlen($raw)>1024*1024) throw new \RuntimeException('Invalid or empty nonce reservation store; reconcile before reuse');
                $state=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
                if(!is_array($state)) throw new \RuntimeException('Invalid nonce reservation state');
            }
            $next=$state[$key]??$pendingNonce;
            if(!is_string($next)) throw new \RuntimeException('Invalid stored nonce');
            Units::uint($next);
            $nonce=bccomp($pendingNonce,$next,0)>0?$pendingNonce:$next;
            if(bccomp($nonce,'18446744073709551615',0)>=0) throw new \RuntimeException('Nonce exhausted');
            $state[$key]=bcadd($nonce,'1',0);
            $encoded=json_encode($state,JSON_THROW_ON_ERROR);
            $temporary=tempnam(dirname($this->path),'.decimal-nonce-');
            if($temporary===false) throw new \RuntimeException('Cannot create nonce reservation snapshot');
            $data=@fopen($temporary,'wb');
            if($data===false) throw new \RuntimeException('Cannot open nonce reservation snapshot');
            try {
                if(fwrite($data,$encoded)!==strlen($encoded) || !fflush($data) || (function_exists('fsync')&&!fsync($data))) throw new \RuntimeException('Cannot flush nonce reservation snapshot');
            } finally { fclose($data); }
            if(!@rename($temporary,$this->path)) throw new \RuntimeException('Cannot atomically replace nonce reservation store');
            $temporary=null;
            return $nonce;
        } finally {
            if($temporary!==null&&is_file($temporary)) unlink($temporary);
            flock($lock,LOCK_UN);fclose($lock);
        }
    }
}
