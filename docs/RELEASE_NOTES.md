# Decimal PHP SDK v0.1.2

Audited patch release with independent EVM, Cosmos, transport and core review. No blockchain transactions were submitted.

## Fixed

- Support the new Decimal stake holdStartTime at index 7, preserving holdTimestamp at index 6 and legacy seven-field responses. Strict canonical decoding covers generated reads and nested frozen stakes. See docs/STAKING_COMPATIBILITY_0_1_2.md.

- Standard proxy implementation trust, Safe/beacon caller context and circular pointers.
- WebSocket future-ID response poisoning, envelope validation, byte queue and absolute deadlines.
- Legacy/access-list gasPrice simulation context and malformed nonce snapshots.
- Unicode mnemonic boundary handling, protobuf resource limits, proto2 field presence/required initialization, Cosmos chain-ID and signer-list parity.

## Added and verified

- Source-backed restricted ERC20 multisend and NFT URI helpers.
- Operation-level matrix for all 55 Python catalog entries against Go/C#/PHP evidence.
- Offline checks, including 2119 independent Python protobuf field-wire vectors across 393 descriptors.
- Loopback transports, 100 cross-process nonce reservations, source reproducibility and installed-package tests.

Exact scope and remaining high-level orchestration/DTO differences are recorded in docs/FINAL_AUDIT_0_1_1.md and docs/AUDIT_OPERATION_PARITY.md. Matching ABI selectors are not labeled complete business-workflow parity.

## Install the latest stable SDK

```sh
composer create-project --repository=https://github.com/maxwell2010/decimal-php-sdk/releases/latest/download/packages.json mintcandy/decimal-web3-sdk decimal-php-sdk
```

The permanent metadata URL installs the current stable release and dependencies in a new directory. Composer 2, PHP 8.2+ and curl/bcmath/gmp/mbstring are required; ZIP extraction needs PHP zip or unzip/7z. Existing lockfiles remain pinned until explicitly updated.

The permanent ZIP is https://github.com/maxwell2010/decimal-php-sdk/releases/latest/download/decimal-php-sdk.zip. Versioned ZIP, alias ZIP, packages.json and SHA256SUMS are attached. The previous 0.1.0 and 0.1.1 archives remain available unchanged.

## Offline code example

```php
require 'vendor/autoload.php';

$contract = Decimal\Contract::profile('0x1111111111111111111111111111111111111111', 'Delegation');
$calldata = $contract->encode('getStakes(bytes32[])', [['0x' . str_repeat('00', 32)]]);
echo $calldata, PHP_EOL; // Offline only; substitute actual deployment/stake IDs for reads.
```
