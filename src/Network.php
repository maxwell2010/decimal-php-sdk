<?php
declare(strict_types=1);
namespace Decimal;

final readonly class Network
{
    public function __construct(public int $chainId, public string $rpcUrl, public string $contractCenter)
    {
        if ($chainId < 1 || !filter_var($rpcUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($rpcUrl, PHP_URL_SCHEME), ['http', 'https'], true) || parse_url($rpcUrl, PHP_URL_USER) !== null) throw new \InvalidArgumentException('Invalid network');
        TransactionDraft::address($contractCenter);
    }
    public static function mainnet(): self { return new self(75, 'https://node.decimalchain.com/web3/', '0xc108715a06f76caa96fa2c943ebf05159c29a87d'); }
    public static function testnet(): self { return new self(202020, 'https://testnet-val.decimalchain.com/web3/', '0xbC96b61F137F28F0Da47Cc4Ef06e5f984B565A2E'); }
}
