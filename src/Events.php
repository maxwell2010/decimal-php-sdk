<?php
declare(strict_types=1);
namespace Decimal;
final class Events
{
    public static function decode(array $abi,array $log,?string $expectedAddress=null): array
    {
        if($expectedAddress!==null&&TransactionDraft::address($log['address']??'')!==TransactionDraft::address($expectedAddress)) throw new \InvalidArgumentException('Unexpected log emitter');
        $topics=$log['topics']??[];$codec=new AbiCodec();
        foreach(self::stakeEventLayouts($abi) as $event) {
            if(($event['type']??'')!=='event'||($event['anonymous']??false)) continue;
            $topic='0x'.\kornrunner\Keccak::hash(Contract::signature($event),256);
            if(strtolower($topics[0]??'')!==$topic) continue;
            $plain=array_values(array_filter($event['inputs'],static fn($f)=>!$f['indexed']));
            $values=$codec->decodeParameters(array_map(Contract::type(...),$plain),$log['data']??'0x');$out=[];$i=0;$j=1;
            foreach($event['inputs'] as $n=>$input) {
                $type=Contract::type($input);
                if($input['indexed']) {
                    $v=$topics[$j++]??throw new \InvalidArgumentException('Missing event topic');
                    if(!preg_match('/^0x[0-9a-fA-F]{64}$/D',$v)) throw new \InvalidArgumentException('Invalid event topic');
                    $value=$type==='string'||$type==='bytes'||str_contains($type,'[')||str_starts_with($type,'(')?['indexedHash'=>strtolower($v)]:$codec->decodeParameters([$type],$v)[0];
                } else $value=$values[$i++];
                $out[$input['name']?:$n]=$value;
            }
            if($j!==count($topics)) throw new \InvalidArgumentException('Unexpected event topics');
            return ['event'=>$event['name'],'signature'=>Contract::signature($event),'args'=>$out,'address'=>$log['address']??null];
        }
        throw new \InvalidArgumentException('Unknown event');
    }
    public static function receipt(string $profile,array $receipt,string $address): array
    {
        if(!preg_match('/^[A-Za-z0-9]+$/D',$profile)) throw new \InvalidArgumentException('Invalid profile');
        $path=dirname(__DIR__).'/abi/'.$profile.'.json';if(!is_file($path)) throw new \InvalidArgumentException('Unknown profile');
        if(($receipt['status']??'')!=='0x1') throw new \InvalidArgumentException('Receipt not successful');
        $abi=self::stakeEventLayouts(json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR));$out=[];
        foreach($receipt['logs']??[] as $log) if(strtolower($log['address']??'')===strtolower($address)) {
            $known=false;foreach($abi as $e) if(($e['type']??'')==='event'&&'0x'.\kornrunner\Keccak::hash(Contract::signature($e),256)===strtolower($log['topics'][0]??'')) $known=true;
            if($known) $out[]=self::decode($abi,$log,$address);
        }
        return $out;
    }
    /** Preserve actual receipt signatures; do not apply node-internal log normalization. */
    private static function stakeEventLayouts(array $abi): array
    {
        $out=$abi;
        foreach($abi as $event) {
            if(($event['type']??'')!=='event')continue;
            $changed=false;
            $convert=function(array $input) use (&$convert,&$changed): array {
                if(!str_starts_with($input['type'],'tuple'))return $input;
                $c=$input['components'];$n=count($c);
                $names=['validator','delegator','token','amount','tokenId','tokenType','holdTimestamp','holdStartTime'];
                $types=['address','address','address','uint256','uint256','uint8','uint256','uint256'];
                if(($n===7||$n===8)&&array_column($c,'name')===array_slice($names,0,$n)&&array_column($c,'type')===array_slice($types,0,$n)) {
                    if($n===8)array_pop($c);else $c[]=['name'=>'holdStartTime','type'=>'uint256'];
                    $changed=true;
                } else foreach($c as $i=>$child)$c[$i]=$convert($child);
                $input['components']=$c;return $input;
            };
            foreach($event['inputs'] as $i=>$input)$event['inputs'][$i]=$convert($input);
            if($changed)$out[]=$event;
        }
        return $out;
    }
}
