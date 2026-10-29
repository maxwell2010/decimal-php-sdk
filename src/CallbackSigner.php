<?php
declare(strict_types=1);
namespace Decimal;

/** Adapter for a caller-owned hardware/remote signer. No transport is implicit. */
final readonly class CallbackSigner implements Signer
{
    public function __construct(private string $sender, private \Closure $callback)
    { TransactionDraft::address($sender); }

    public function address(): string { return TransactionDraft::address($this->sender); }

    public function sign(PreparedTransaction $transaction): SignedTransaction
    {
        if(TransactionDraft::address($transaction->draft->from)!==$this->address()) throw new \InvalidArgumentException('Signer does not match sender');
        $result=($this->callback)($transaction);
        $signed=is_string($result)?new SignedTransaction($result,$transaction->chainId):$result;
        if(!$signed instanceof SignedTransaction || $signed->chainId!==$transaction->chainId || $signed->sender!==$this->address() || $signed->unsignedHash!==$transaction->signingHash()) throw new \RuntimeException('External signer changed transaction or returned another signer');
        return $signed;
    }
}
