# Decimal PHP SDK v0.1.0-alpha.1

Independent PHP SDK by MintCandy. Public preview with source archive and locked Composer dependencies, matching the source-package distribution style of the Python SDK.

## Included

- 27 ABI profiles and 577 generated function bindings from Go/Python references, with source hashes and versioned deployment evidence.
- English BIP39/BIP32 wallets, precise units, EIP-155/2930/1559, EIP-712 and checked permit signing.
- Safe EOA signatures, allowance plans, DEL multisend, event decoding and proxy/beacon checks.
- Read RPC, REST and experimental WebSocket subscriptions.
- Offline Cosmos schemas and direct signing verified against Go reference bytes.
- English/Russian README, advanced examples and a detailed parity matrix.

The phpseclib dependency chain has been removed; Composer audit reports no known advisories for the lockfile. Validation includes 1916 offline checks and loopback RPC/API/WebSocket integration tests. No blockchain transactions were submitted.

## Install

Download `decimal-php-sdk-0.1.0-alpha.1.zip`, verify its checksum against `SHA256SUMS`, extract it, then run `composer install --no-dev` inside the package. Requires PHP 8.2+ with curl, bcmath, gmp and mbstring. Git is not required for archive installation. Packagist publication is separate.

## Balance example

```php
require 'vendor/autoload.php';
use Decimal\{Client, Network, Units};
$address = getenv('WALLET_ADDRESS') ?: throw new RuntimeException('Set WALLET_ADDRESS');
echo Units::format((new Client(Network::testnet()))->balance($address)), " DEL\n";
```

This is an alpha, not full Go/Python/C# parity. Complete business-workflow orchestration, upstream Cosmos ValidateBasic/ante validation, Cosmos gRPC/fee convergence and some advanced Safe policies remain incomplete. See docs/PARITY.md. Funded-wallet acceptance and an external cryptographic audit have not been performed.
