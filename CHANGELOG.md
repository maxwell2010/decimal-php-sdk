# Changelog

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
