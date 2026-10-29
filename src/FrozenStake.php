<?php
declare(strict_types=1);
namespace Decimal;
final readonly class FrozenStake
{
    public function __construct(public string $index,public array $stake,public string $status,public string $freezeType,public string $unfreezeTimestamp,public string $block)
    { Units::uint($index);Units::uint($status);Units::uint($freezeType);Units::uint($unfreezeTimestamp); }
    public function active(): bool { return $this->freezeType==='1' && $this->status==='1' && $this->stake['amount']!=='0'; }
    public function matured(string $unixSeconds): bool
    { Units::uint($unixSeconds); return $this->active() && $this->unfreezeTimestamp!=='0' && bccomp($this->unfreezeTimestamp,$unixSeconds,0)<=0; }
}
