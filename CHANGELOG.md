# Changelog

## 0.1.2 - 2026-10-30

- Support the updated Decimal stake output with holdStartTime at index 7, preserving holdTimestamp at index 6 and legacy seven-field responses.
- Keep strict canonical ABI validation for old and new stake layouts; never replace an unavailable or malformed stake with zero.
- Cover generated contract reads, StakingReader and nested frozen stake outputs with offline regression tests.

See docs/STAKING_COMPATIBILITY_0_1_2.md for pinned upstream evidence and compatibility boundaries. No blockchain transactions were submitted. Published 0.1.0 and 0.1.1 assets remain unchanged.

## 0.1.1 - 2026-10-29

Audited patch release. The immutable 0.1.0 release remains available.

- Bind standard proxy verification to trusted implementation/runtime evidence; preserve correct Safe/beacon caller contexts and reject circular pointers.
- Reject unsolicited WebSocket response IDs, malformed envelopes and oversized queues; enforce absolute transport deadlines.
- Resolve gas price before legacy/access-list call/estimate so simulation uses the prepared transaction's fee context.
- Reject structurally corrupt, duplicate or null nonce snapshots before reserving another nonce.
- Match the Python SDK mnemonic wrapper's Unicode boundary whitespace handling and reject invalid UTF-8.
- Bound protobuf encoding, preserve proto2 explicit-default presence, enforce required-field initialization and align chain-ID/signer-list semantics with Go.
- Add source-backed restricted ERC20 multisend and simulated NFT URI helpers.
- Add a 55-operation parity matrix, independent 2119-message-field protobuf fixtures and adversarial transport tests.

No blockchain broadcasts were performed. Remaining high-level orchestration/DTO distinctions are explicitly recorded in docs/AUDIT_OPERATION_PARITY.md.

## 0.1.0 - 2026-10-29

First stable source release, with a gated GitHub publication workflow and permanent latest installation links.

- Add contract creation, exact callback signer binding, uint64 envelope validation, fee reserve/estimate separation and cooperative cancellation.
- Add atomic cross-process nonce reservations and corrupt-state rejection; verify 100 concurrent reservations.
- Add weighted Safe snapshots and mixed EOA/personal/EIP-1271/prevalidated signatures with reviewed delegatecall guards.
- Add trusted deployment bindings, pinned verified reads, frozen-stake completion, guarded NFT/delegation and reserveless-token helpers, deployed native-reserve mint/create, receipt extraction and password checks.
- Add bounded chain-checked read failover without replaying writes, strict API paths, indexer helpers, notification queues and explicit reconnect/resubscribe.
- Add all 43 pinned Cosmos ValidateBasic policies, signer extraction/order, strict protobuf decode, source-correct packed fields, direct envelope checks, fee convergence and native HTTP/2 query/simulation.
- Include actual-node policy corpora, independent Python calldata/signature/protobuf fixtures, loopback transports and installed-package tests.
- Publish versioned and permanent-name source ZIPs, SHA256SUMS and latest Composer repository metadata. Packagist is not required.

No blockchain transactions were submitted. Deployment state, account roles and ante/execution admission remain node-authoritative; source-level compatibility does not claim funded-wallet acceptance. See docs/PARITY.md.

## 0.1.0-alpha.1 - 2026-10-29

Initial public preview with 27 ABI profiles, 577 bindings, wallets, EVM and offline Cosmos signing, examples and independent tests. Removed the phpseclib dependency chain and its Composer advisory. The stable release above closes the documented source-level implementation gaps within its explicit scope.
