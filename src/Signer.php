<?php
declare(strict_types=1);
namespace Decimal;

interface Signer
{
    public function address(): string;
    public function sign(PreparedTransaction $transaction): SignedTransaction;
}
