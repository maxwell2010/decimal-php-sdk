# Decimal PHP SDK v0.1.5

Read-only mainnet/testnet validation and RPC transport diagnostics patch. No blockchain transactions, actual approvals, signing or deployments were performed.

## Fixed

RPC failures now preserve cURL errno/error, HTTP status and response-limit details in RpcTransportException::rpcData. A TLS failure no longer appears as an unexplained status 0. TLS verification, read failover and write retry policy are unchanged.

## Verified

- 429 recorded guarded RPC attempts and 159 passed checks across mainnet/testnet.
- Active Stake8, validator/state/quote/NFT/Checks reads, legacy getter selection, actual historical receipt and event decoding.
- Nonpersistent zero-value ERC20 approve/transfer eth_call successes and expected over-balance reverts.
- 7947 offline checks, recorded response replays and loopback transport diagnostics. CI gates publication on PHP 8.2-8.5 and Linux/Windows/macOS.

[Live audit and captured evidence](https://github.com/maxwell2010/decimal-php-sdk/blob/main/docs/LIVE_RPC_AUDIT_0_1_5.md) records block snapshots, actual coverage and limits. Missing bridge registry targets and unsupported optional legacy views remain explicit; a funded NFT lifecycle or bridge settlement is not claimed.

## Install the latest stable release

```sh
composer create-project --repository=https://github.com/maxwell2010/decimal-php-sdk/releases/latest/download/packages.json mintcandy/decimal-web3-sdk decimal-php-sdk
```

Composer 2, PHP 8.2+ and curl/bcmath/gmp/mbstring are required; ZIP extraction needs PHP zip or unzip/7z. Configure a trusted curl.cainfo bundle when your PHP installation lacks CA trust. Do not disable TLS verification.

```php
require 'vendor/autoload.php';

$client = new Decimal\Client(Decimal\Network::mainnet());
$client->verifyChain();
$contracts = $client->resolveSystemContracts(); // Read-only, pinned registry snapshot.
echo json_encode($contracts);
```

[Permanent ZIP](https://github.com/maxwell2010/decimal-php-sdk/releases/latest/download/decimal-php-sdk.zip) and [SHA256SUMS](https://github.com/maxwell2010/decimal-php-sdk/releases/latest/download/SHA256SUMS) follow the latest stable release. Versioned ZIP, alias ZIP, packages.json and SHA256SUMS are attached. Prior versioned assets remain immutable.
