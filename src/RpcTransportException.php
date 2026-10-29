<?php
declare(strict_types=1);
namespace Decimal;
/** A transport failure is ambiguous for a submitted write and must never be retried automatically. */
final class RpcTransportException extends RpcException {}
