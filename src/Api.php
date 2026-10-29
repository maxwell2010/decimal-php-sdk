<?php
declare(strict_types=1);
namespace Decimal;
final class Api
{
    public function __construct(private readonly string $base,private readonly ?\Closure $transport=null)
    {
        $u=parse_url($base);
        if(!filter_var($base,FILTER_VALIDATE_URL)||!in_array($u['scheme']??'',['http','https'],true)||isset($u['user'])||isset($u['query'])||isset($u['fragment'])) throw new \InvalidArgumentException('Invalid API URL');
    }
    public function get(string $path,array $query=[]): mixed
    {
        $decoded=rawurldecode($path);
        if(!preg_match('~^[A-Za-z0-9_/%.-]+$~D',$path)||str_starts_with($path,'/')||str_contains($decoded,'\\')||str_contains($decoded,':')||str_contains($decoded,'?')||str_contains($decoded,'#')) throw new \InvalidArgumentException('Expected relative API path');
        foreach(explode('/',$decoded) as $part) if($part==='.'||$part==='..') throw new \InvalidArgumentException('Invalid API path segment');
        $url=rtrim($this->base,'/').'/'.$path.($query?'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986):'');
        if($this->transport) return ($this->transport)($url);
        $curl=curl_init($url);$body='';
        curl_setopt_array($curl,[CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_WRITEFUNCTION=>static function($c,$part)use(&$body){if(strlen($body)+strlen($part)>8*1024*1024)return 0;$body.=$part;return strlen($part);}]);
        $ok=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
        if($ok===false||$status<200||$status>=300) throw new \RuntimeException('API transport failed');
        return json_decode($body,true,512,JSON_THROW_ON_ERROR);
    }
    public static function page(int $limit=50,int $offset=0): array { if($limit<1||$limit>500||$offset<0) throw new \InvalidArgumentException('Invalid page');return compact('limit','offset'); }
    public function validators(): mixed { return $this->get('validators'); }
    public function walletStakes(string $address): mixed { return $this->get('validators/wallet/'.TransactionDraft::address($address).'/stakes/coins'); }
    public function walletUnstakes(string $address): mixed { return $this->get('validators/'.TransactionDraft::address($address).'/unstakes/coins'); }
}
