# Decimal Web3 SDK for PHP

Independent Decimal SDK by [MintCandy](https://mintcandy.ru/) and [@Maxwell2019](https://t.me/Maxwell2019). PHP 8.2+, MIT license.

**v0.1.0-alpha.1 is a preview, not a claim of full Go/Python/C# parity.** Includes 27 ABI profiles, 577 generated function bindings, EVM transaction signing and offline Cosmos codecs. Read the [coverage matrix](docs/PARITY.md) before choosing a workflow.

[Русский README](README.ru.md) | [Examples](examples) | [Release notes](CHANGELOG.md) | [Security](SECURITY.md)

## Install without Git

Download the source package from [GitHub Releases](https://github.com/maxwell2010/decimal-php-sdk/releases/tag/v0.1.0-alpha.1), extract it, then run Composer inside the extracted directory:

```sh
composer install --no-dev
php examples/offline.php
```

Requires PHP 8.2 or newer and extensions curl, bcmath, gmp and mbstring. Linux, Windows and macOS use the same sources. GitHub is the distribution source; this package is not published on Packagist. The archive includes composer.lock to pin dependencies. To use it from another project before Packagist publication, register the GitHub repository as a Composer VCS repository and require `mintcandy/decimal-web3-sdk:0.1.0-alpha.1`.

On this Windows development machine gmp exists but is disabled in php.ini. Run commands without changing global settings:

```powershell
php -d extension=gmp C:/composer/composer.phar install
php -d extension=gmp tests/run.php
```

## Read a balance

```php
require 'vendor/autoload.php';

use Decimal\{Client, Network, Units};

$client = new Client(Network::mainnet());
$address = getenv('WALLET_ADDRESS') ?: throw new RuntimeException('Set WALLET_ADDRESS');
echo Units::format($client->balance($address)), " DEL\n";
```

Mainnet uses chain ID 75; `Network::testnet()` selects 202020. A custom RPC can be provided through `new Network($chainId, $rpcUrl, $contractCenter)`. The client verifies eth_chainId before queries, simulation and submission. No signer is needed for reads.

## Derive a wallet and prepare a transfer

```php
use Decimal\{Mnemonic, TransactionDraft};

$phrase = getenv('DECIMAL_MNEMONIC') ?: throw new RuntimeException('Set DECIMAL_MNEMONIC');
$wallet = Mnemonic::wallet($phrase, index: 0); // m/44'/60'/0'/0/0
$recipient = getenv('RECIPIENT') ?: throw new RuntimeException('Set RECIPIENT');
$draft = TransactionDraft::del($wallet->address, $recipient, '0.01', 'hello');
$prepared = $client->prepare($draft, feeCap: Units::parse('0.1'));
echo Units::format($prepared->maximumFee()), " DEL maximum fee\n";
$signed = $wallet->sign($prepared); // Local signing only.
// Sending remains a separate, explicit application decision:
// $hash = $client->broadcast($signed);
// $receipt = $client->waitReceipt($hash);
```

Keep phrases and private keys outside source code and logs. Amounts and ABI integers are decimal strings, bytes are 0x-prefixed hex strings, arrays and tuples are positional PHP lists. The default prepare path uses legacy EIP-155. EIP-2930 and EIP-1559 are also supported:

```php
$prepared = $client->prepare($draft, type: 2,
    maxFeePerGas: Units::parse('1', 9),
    maxPriorityFee: Units::parse('0.1', 9));
```

Explicit dynamic fee caps avoid silently guessing network policy. Estimate/prepare may perform read RPC calls; wallet signing performs no network I/O. Pending nonce allocation is not coordinated between concurrent applications.

## ERC20 and other contracts

```php
use Decimal\Contract;

$tokenAddress = getenv('TOKEN_ADDRESS') ?: throw new RuntimeException('Set TOKEN_ADDRESS');
$token = Contract::profile($tokenAddress, 'Token');
$decimals = (int)$token->call($client, 'decimals()')[0];
$draft = $token->draft($wallet->address, 'transfer(address,uint256)', [
    $recipient, Units::parse('2', $decimals),
]);
$balance = $token->call($client, 'balanceOf(address)', [$wallet->address])[0];
```

ABI-decoded integers are decimal strings. Generated `Decimal\Contracts\*` classes expose explicit argument types; their stable method suffixes disambiguate PHP names and Solidity overloads. [operations.json](docs/operations.json) lists each signature and PHP method. `ReferenceOperations` exposes the Go reference operation routes with contract-family restrictions. An ABI route is a builder, not a complete approval/permit/business workflow.

Additional modules cover EIP-712 and permit, Safe transaction hashes and EOA signatures, allowance plans, DEL multisend, event receipt decoding, EIP-1967 implementation/beacon checks, GET APIs, Bech32 conversion, WebSocket subscriptions, and offline Cosmos direct signing. See [advanced examples](docs/EXAMPLES.md).

## Sources and compatibility

ABI snapshots come from the existing Go/Python SDKs. The node source is pinned at `9e6c6d718d662083c4a524376a66d2c50bd4bc77`. Source URLs, hashes and deployment evidence are in `docs/*sources.json`; packaged ABI hashes are in [abi-hashes.json](docs/abi-hashes.json). Python reference files can include unreleased local changes, explicitly recorded in [python-sources.json](docs/python-sources.json).

`Testnet*20261029` profiles describe observed testnet artifacts, not arbitrary contracts or mainnet deployments. Use `Deployment::verify` with independently trusted runtime hashes and implementation addresses before preparing calls. Standalone ABI builders do not automatically verify a deployment. Resolve network-specific contracts from the Contract Center rather than copying addresses between networks.

## Development and validation

```sh
composer install
composer test
composer audit
python tools/generate.py
python tools/vectors.py
```

Tests use offline fixtures and injected RPC responses. They compare every bundled function's calldata and decoded inputs with Python eth-abi, signing with eth-account, and Cosmos envelope/signature bytes with the Go reference fixture. No funded-wallet acceptance test or blockchain broadcast was performed. WebSocket tests cover frame encoding and a loopback handshake/subscribe/ping/fragment/unsubscribe server, not a live provider campaign.

The dependency chain containing phpseclib was removed rather than suppressing its advisory. Composer audit reports no known advisories for the current lockfile; this is not a cryptographic security audit. ABI, RLP and protobuf codecs remain subject to the preview limitations in [PARITY.md](docs/PARITY.md).
