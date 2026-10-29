<?php
declare(strict_types=1);
namespace Decimal;

/** Cooperative cancellation between bounded synchronous RPC operations. */
final class CancellationToken
{
    private bool $cancelled=false;
    public function cancel(): void { $this->cancelled=true; }
    public function throwIfCancelled(): void
    { if($this->cancelled) throw new \RuntimeException('Operation cancelled'); }
}
