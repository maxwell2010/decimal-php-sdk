<?php
declare(strict_types=1);
namespace Decimal;
final readonly class StakePosition
{
    public array $fields;
    public function __construct(array $tuple)
    {
        if(!array_is_list($tuple)||!in_array(count($tuple),[7,8],true))throw new \InvalidArgumentException('Unknown stake layout');
        foreach(array_slice($tuple,0,3) as $a)TransactionDraft::address($a);foreach(array_slice($tuple,3) as $v)Units::uint($v);
        if(bccomp($tuple[5],'255',0)>0)throw new \InvalidArgumentException('Stake tokenType must fit uint8');
        $this->fields=array_combine(array_slice(['validator','delegator','token','amount','tokenId','tokenType','holdTimestamp','holdStartTime'],0,count($tuple)),$tuple);
    }
    public function exists(): bool {return $this->fields['amount']!=='0';}
    public function isHold(): bool {return $this->fields['holdTimestamp']!=='0';}
    public function matured(string $timestamp): bool {Units::uint($timestamp);return $this->isHold()&&bccomp($this->fields['holdTimestamp'],$timestamp,0)<=0;}
    public function amount(int $decimals=18): string {return Units::format($this->fields['amount'],$decimals);}
}
