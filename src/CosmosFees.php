<?php
declare(strict_types=1);
namespace Decimal;

/** Fixed-point signed-byte fee calculation matching the Go SDK callback contract. */
final class CosmosFees
{
    public static function converge(\Closure $build,\Closure $quote,string $maxFee,string $snapshot,string $initialFee='1',int $iterations=16,int $maxBytes=1048576): array
    {
        Units::uint($maxFee);Units::uint($initialFee);
        if($snapshot===''||$iterations<1||$iterations>1024||$maxBytes<1||$maxBytes>67108864)throw new \InvalidArgumentException('Invalid Cosmos fee bounds');
        $fee=$initialFee;$seen=[];
        for($i=1;$i<=$iterations;++$i){
            if(bccomp($fee,$maxFee,0)>0)throw new \RuntimeException('Cosmos fee exceeds cap');
            if(isset($seen[$fee]))throw new \RuntimeException('Cosmos fee cycle');$seen[$fee]=true;
            $bytes=$build($fee);
            if(!is_string($bytes)||$bytes===''||strlen($bytes)>$maxBytes)throw new \RuntimeException('Invalid Cosmos signed byte size');
            $result=$quote(['fee'=>$fee,'signedBytes'=>$bytes,'signedLength'=>strlen($bytes),'snapshot'=>$snapshot]);
            if(!is_array($result)||($result['snapshot']??null)!==$snapshot)throw new \RuntimeException('Cosmos fee snapshot mismatch');
            $next=Units::uint($result['fee']??'');
            if(bccomp($next,$maxFee,0)>0)throw new \RuntimeException('Cosmos quoted fee exceeds cap');
            if($next===$fee)return ['fee'=>$fee,'signedBytes'=>$bytes,'iterations'=>$i,'snapshot'=>$snapshot];
            $fee=$next;
        }
        throw new \RuntimeException('Cosmos fee convergence exhausted');
    }
}
