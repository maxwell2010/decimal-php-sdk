# NFT follow-up and pinned simulation

Three agents extended the 0.1.6 public-source and read-only investigation. Two final saved RPC artifacts contain 42 permitted requests: 12 legacy custody/pause reads and simulations, and 30 bounded position-discovery requests. Public HTTP source/metadata requests are recorded separately. No transaction, signature, estimate, deployment or state override was performed.

## Resolved diagnosis

The old 0.1.6 narrative did not decode custom errors already present in its raw artifacts. The new [legacy custody diagnosis](NFT_LEGACY_CUSTODY_DIAGNOSIS.md) reproduces those payloads at the same mainnet block and reads paused() as true. complete([0]) returns EnforcedPause(); ownerOf(1) and an independent custody transfer return ERC721NonexistentToken(1). Pause is an immediate admission blocker in the observed state. This does not prove that unpausing would repair the absent token or make completion succeed. No administrative action was attempted.

## New real NFT coverage

The [bounded position search](NFT_POSITION_SEARCH.md) finds a real testnet ERC721 position active at its historical event block. The exact delegator's withdrawal succeeds through eth_call at that block. The position is inactive at the latest snapshot. Mainnet funded unified exits, held positions, ERC1155 funded exits and a persisted complete lifecycle remain outside this evidence. A finite log horizon does not establish their absence.

## SDK changes

- Current ERC721 transfer rejects amount other than 1 before RPC, instead of discarding the requested amount. Nonempty ERC721 data rejects with guidance to the explicit transferFrom helper; its four-argument safe transfer preserves the payload. Current ERC1155 transfers preserve their data and require a positive canonical amount.
- Client::simulate($draft, $block = 'pending') accepts a chosen canonical block number or standard block tag. The default stays pending. Simulation verifies the chain and invokes eth_call only; it does not sign, estimate or broadcast.
- RpcException::revertReason($errorAbi = []) decodes direct hex error data without changing the original code, message or rpcData. RevertReason handles standard Error(string), Panic(uint256), explicitly supplied custom error ABIs, malformed payloads and ambiguous custom selectors. Empty data remains empty; unknown data remains unknown. Built-in selectors retain their standard interpretation. A supplied custom ABI and a matching selector do not authenticate the originating contract; errors can bubble up or be fabricated. Nested provider objects and transport diagnostics are not guessed into revert bytes.

Decoding follows primary [Solidity error encoding](https://docs.soliditylang.org/en/latest/abi-spec.html#errors) and [panic definitions](https://docs.soliditylang.org/en/latest/control-structures.html#panic-via-assert-and-error-via-require). The new replays preserve exact caller, deployment, calldata and block, including the real pause/missing-token errors and historical withdrawal.

## Remaining Bridge boundary

The [additional public-source search](BRIDGE_PUBLIC_FOLLOWUP.md) still does not establish a current mainnet Bridge or complete authenticated ABI for the testnet candidate. No speculative deployment or ABI profile was introduced. The 0.1.6 verified-binding guards remain in place. Completing Bridge settlement validation requires evidence that is not yet available through the checked public sources.

## Example

```php
// Existing client and unsigned draft; no wallet or private key is required.
$errors = [['type'=>'error', 'name'=>'EnforcedPause', 'inputs'=>[]]];
try {
    $returnBytes = $client->simulate($draft, '0x20c281d');
} catch (Decimal\RpcException $error) {
    echo json_encode($error->revertReason($errors));
    // Raw code/message/rpcData are still available for diagnosis.
}
```

Independent simulations do not persist state or guarantee future admission. Live tools remain opt-in; CI uses offline replays. Permanent latest installation URLs remain unchanged, and prior versioned release assets remain immutable.

The complete offline suite passes 8195 checks, including installed-package tests. [Request totals](live/nft-followup-summary.json) enumerate the saved final runs; exploratory attempts are not counted as accepted evidence.
