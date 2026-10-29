<?php
declare(strict_types=1);
namespace Decimal;
final class RpcException extends \RuntimeException
{
    public function __construct(string $message, int $code = 0, public readonly mixed $rpcData = null) { parent::__construct($message, $code); }
}
