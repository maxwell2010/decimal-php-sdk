# Decimal PHP SDK v0.1.7

NFT transfer input fixes, simulation on a chosen block, strict Solidity revert decoding and further real read-only NFT validation. All live checks used reads and eth_call, without transactions, signatures, estimates or state overrides.

## Changes

- Reject ignored ERC721 transfer amounts and nonempty data before RPC. Explicit transferFrom retains the data-bearing safe-transfer variant; ERC1155 amounts must be positive canonical integers.
- Client::simulate accepts an optional block, preserving pending as the default.
- RpcException::revertReason and RevertReason decode Error(string), Panic(uint256) and caller-supplied custom error ABI, preserve raw errors and distinguish malformed, empty, unknown and ambiguous custom payloads.
- Identify mainnet legacy complete([0]) pause blocker and absent NFT at the pinned block. Correct the 0.1.6 narrative: custom error bytes were already present in the earlier raw captures.
- Successfully simulate withdrawal of a real historical funded unified ERC721 position on testnet. Preserve 42 additional permitted RPC requests and exact offline replays.
- 8195 offline checks; CI covers Linux/Windows/macOS and PHP 8.2-8.5, Composer audit and installed-package validation.

## Example

```php
// Existing client and unsigned draft; no wallet or key is required.
$errors = [['type'=>'error', 'name'=>'EnforcedPause', 'inputs'=>[]]];
try {
    $bytes = $client->simulate($draft, '0x20c281d');
} catch (Decimal\RpcException $error) {
    echo json_encode($error->revertReason($errors));
}
```

Choose a block and draft appropriate to your selected network. [Simulation usage and limitations](https://github.com/maxwell2010/decimal-php-sdk/blob/v0.1.7/docs/SIMULATION.md).

## Remaining limits

A current mainnet Bridge and authenticated full testnet candidate ABI remain unconfirmed after further public-source research. Legacy completion is paused in the observed state; unpausing is not a demonstrated repair for the absent token. The historical testnet withdrawal does not establish a current funded exit, held/ERC1155 coverage or a persisted lifecycle.

[Full audit and evidence](https://github.com/maxwell2010/decimal-php-sdk/blob/v0.1.7/docs/NFT_FOLLOWUP_0_1_7.md).

## Install the latest stable version

```sh
composer create-project --repository=https://github.com/maxwell2010/decimal-php-sdk/releases/latest/download/packages.json mintcandy/decimal-web3-sdk decimal-php-sdk
```

Composer 2, PHP 8.2+, curl/bcmath/gmp/mbstring and a ZIP extractor are required. Configure a trusted curl.cainfo bundle when needed; TLS verification stays enabled. Permanent latest links stay unchanged; prior versioned assets remain immutable.
