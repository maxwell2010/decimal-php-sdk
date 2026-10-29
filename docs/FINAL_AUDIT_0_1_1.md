# Final audit of PHP SDK 0.1.0, fixes in 0.1.1

Three independent agents audited EVM workflows, Cosmos wire/signing/validation and transports. The parent audited core fee preparation, nonce persistence, mnemonic handling and distribution. All reference repositories were read-only. No mainnet or testnet transaction was submitted.

## Confirmed fixes

- Standard proxy verification previously allowed runtime-only evidence even when an implementation slot/beacon was present. The checked binding now requires implementation address and runtime evidence. Beacon reads preserve the proxy caller and reject circular/self/zero implementation pointers.
- EIP-1271 owner validation now calls from the Safe, matching real contract-owner execution context. Zero Safe domains/policies are rejected, matching the Go constructor.
- WebSocket response IDs must match the outstanding blocking request; a server cannot prequeue a future response and poison the next subscription. Queue bytes, envelopes and absolute deadlines are bounded.
- Legacy/EIP-2930 simulation and gas estimation now receive the selected gasPrice. Previously the price was resolved afterward, permitting GASPRICE-dependent contract behavior to differ from the prepared transaction.
- Nonce snapshots are validated as opaque canonical nonempty maps of chain/address to uint64 strings. Null, numeric, duplicate, invalid-key and invalid-range snapshots fail before state changes, preserving fail-closed behavior.
- Mnemonic outer Unicode whitespace now follows the Python SDK wrapper's strip/NFKD behavior. Invalid UTF-8 is rejected explicitly.
- Protobuf encode now has the same resource/depth bounds as decode. Import metadata preserves proto2 presence and required fields. Independent field-wire fixtures found 74 explicit-default mismatches that are corrected; required-field partial messages are rejected.
- Cosmos chain IDs no longer have an invented 128-byte restriction absent from the reference Go codec, and signer arrays must be positional lists.

## Added source-backed helpers

RestrictedMultisend implements an explicitly reviewed sender-restricted deployment, bound to chain/runtime and proxy evidence, balance checks, separate allowance/reset steps and checked permit. It does not authorize arbitrary Multicall as a token spender. NftService::setTokenUri uses only the already bundled Python ERC721/ERC1155 URI profiles and propagates simulation/permission failures.

## Parity conclusion

[The 55-entry operation matrix](AUDIT_OPERATION_PARITY.md) records exact local Python source hashes/lines, Go candidates, C# signature evidence and PHP helper/ABI routes. An early draft incorrectly described the URI ABI as absent; the published matrix correctly identifies the existing profiles and new helper.

Full semantic parity for every high-level economic/staking/reset/bridge workflow and language-specific report/DTO is not claimed. Many such operations have exact ABI builders but do not perform the same automatic orchestration as another SDK. Deployment compatibility, active permissions, balances, proof production and node ante/execution acceptance remain external mutable state. These distinctions are retained instead of reporting selector coverage as completed workflow acceptance.

## Validation and release

The shared PHP suite includes 6779 checks, including 2119 independent official Python protobuf vectors across all 393 imported descriptors. Initialized fixtures must match wire bytes and decode/re-encode; incomplete required-field fixtures must reject. No maps occur in the pinned descriptor set. The original descriptor hash and reproducible importer/vector tools are packaged.

Additional checks cover independent ABI/EVM/checks/multisend fixtures, four loopback WebSocket scenarios, RPC/API loopback, 100 cross-process nonce reservations, generated binding reproducibility, Composer audit, and a single-command installation of the built archive. CI gates release publication on Windows/Linux/macOS and PHP 8.2-8.5.

Detailed evidence: [EVM audit](AUDIT_EVM_0_1_1.md), [Cosmos audit](AUDIT_COSMOS_0_1_1.md), [transport audit](AUDIT_TRANSPORT_0_1_1.md). The latest installation URLs remain unchanged, while versioned source archives are immutable.
