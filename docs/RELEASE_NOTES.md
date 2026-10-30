# Decimal PHP SDK v0.1.6

Bridge discovery, extended NFT eth_call validation and verified Bridge approval planning. Live validation used read RPC and eth_call only, without transaction sends, signatures, estimates or deployment.

## Added and changed

- BridgeService::verified / Sdk::verifiedBridge: caller-reviewed runtime and implementation evidence, exact outgoing route binding and an actual uint16 fee-interface probe.
- Bridge auto-approval now requires the verified binding. Fees below the reported minimum, another route, changed runtime or an incompatible fee interface reject before an approval plan. Raw unsigned builders remain available with autoApprove=false.
- Recorded 195 additional bounded RPC attempts, including successful mainnet/testnet NFT action simulations and legacy/unified NFT reads. Source/metadata discovery requests are recorded separately.
- 8095 offline checks, new real-response replays and verified-binding regressions.

## Explicit remaining limits

A current mainnet Bridge and a complete authenticated ABI for the testnet candidate were not established. The candidate rejects uint16 fee calls, accepts an exploratory uint256 fee call, and is not the Token implementation claimed by the compact API metadata. No ABI is silently rewritten and no address is guessed.

Mainnet legacy NFT frozen index 0 is mature but complete([0]) reverts; asset reads also show ownerOf(1) reverting while frozen/reserve metadata remain. The exact cause is unresolved and the exit is not reported successful. Independent NFT action eth_call successes do not compose a persisted lifecycle.

[Full findings, sources and raw RPC evidence](https://github.com/maxwell2010/decimal-php-sdk/blob/main/docs/BRIDGE_NFT_AUDIT_0_1_6.md).

## Install the latest stable SDK

```sh
composer create-project --repository=https://github.com/maxwell2010/decimal-php-sdk/releases/latest/download/packages.json mintcandy/decimal-web3-sdk decimal-php-sdk
```

Composer 2, PHP 8.2+, curl/bcmath/gmp/mbstring and a ZIP extractor are required. Configure a trusted curl.cainfo bundle when needed; TLS verification stays enabled. Release CI covers Linux/Windows/macOS and PHP 8.2-8.5, Composer audit and installed-package tests. Prior versioned assets remain immutable; permanent latest links remain unchanged.

```php
require 'vendor/autoload.php';
$client = new Decimal\Client(Decimal\Network::testnet());
$client->verifyChain();
$contracts = $client->resolveSystemContracts(); // Read-only snapshot; not Bridge discovery.
echo json_encode($contracts);
```
