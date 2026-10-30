<?php
declare(strict_types=1);
namespace Decimal;
class RpcException extends \RuntimeException
{
    public function __construct(string $message, int $code = 0, public readonly mixed $rpcData = null) { parent::__construct($message, $code); }
    /** Only direct hex error data is interpreted. Raw RPC fields remain unchanged. */
    public function revertReason(array $errorAbi = []): ?array
    {
        if ($this instanceof RpcTransportException || !is_string($this->rpcData) || !preg_match('/^0x(?:[a-fA-F0-9]{2})*$/D', $this->rpcData)) return null;
        return RevertReason::decode($this->rpcData, $errorAbi);
    }
}
