# Changelog

## 0.1.7 - 2026-10-30

- Reject ignored ERC721 transfer amounts/data before RPC; preserve explicit data-bearing transfers and validate ERC1155 amounts.
- Add an optional pinned block to Client::simulate and strict Solidity Error/Panic/custom-ABI diagnostics through RpcException::revertReason and RevertReason, preserving raw RPC errors.
- Diagnose the legacy mainnet completion as paused and the observed NFT as nonexistent at the pinned state; correct the earlier narrative's failure to interpret already captured custom errors.
- Verify a real historical funded unified ERC721 withdrawal through testnet eth_call. Record 42 additional permitted requests and extend offline replays and input/collision regressions to 8195 checks.

No transactions or account signatures were performed. Bridge provenance and remaining funded lifecycle limits are explicit in docs/NFT_FOLLOWUP_0_1_7.md. Existing versioned assets remain immutable.

## 0.1.6 - 2026-10-30

- Add caller-reviewed runtime/implementation-bound Bridge factory, route binding, concrete verified quote block and reported-minimum service-fee guards.
- Require verified deployment binding for Bridge auto-approval plans; raw builders remain available without automatic approval. No global uint256 ABI rewrite is performed.
- Capture current bridge discovery and 195 bounded read/eth_call attempts, including actual NFT mint/transfer/burn/approval/batch calls and legacy/unified position reads.
- Add recorded-response and binding regressions: 8095 offline checks.

No transactions or account signatures were performed in the live audit. A current mainnet Bridge/full testnet ABI remain unconfirmed; mature legacy NFT completion revert remains unresolved. See docs/BRIDGE_NFT_AUDIT_0_1_6.md.

## 0.1.5 - 2026-10-30

- Preserve cURL errno/error, HTTP status and response-limit diagnostics before closing the RPC handle. TLS verification and retry behavior remain unchanged.
- Record mainnet/testnet read-only acceptance: 429 guarded RPC attempts, 159 passed checks, current stake/event/token/NFT/Checks reads and nonpersistent ERC20 eth_call probes.
- Add replay fixtures, a strict live-audit method guard and loopback HTTP/connection/size/failover regressions. The offline suite passes 7947 checks.

No actual approvals, signatures, transaction broadcasts or deployment were performed. Optional legacy getters, missing registry slugs and unfunded NFT/bridge coverage limits are documented in docs/LIVE_RPC_AUDIT_0_1_5.md.

## 0.1.4 - 2026-10-30

- Extend completed operation parity to the newer recovery-014 Python request branches and guards.
- Add current token/NFT profiles and Sdk factories while retaining explicit original/raw services and legacy position routes.
- Add current testnet fees/symbols, two-step token updates, GasCenter permits, structured validator metadata, completion/legacy penalty requests and externally supplied staking permits.
- Add checked NFT reserve/mint/permit/whole-position exits, tuple6 collections and seven legacy NFT hold/reset/complete requests.
- Add fee aliases, transaction refresh without resending, checks signer/hash helpers and confirmed non-atomic sequence execution with state refresh.
- Add independent current-profile fixtures and negative regressions: 7872 offline checks.

No blockchain transactions were submitted. See docs/CURRENT_PARITY_0_1_4.md. Published versioned assets remain immutable.

## 0.1.3 - 2026-10-30

- Close the remaining 55-entry operation matrix with 54 named request workflows and an explicit safe-policy rejection of arbitrary Multicall ERC20 allowance; retain verified RestrictedMultisend.
- Add ERC20/token economy/quotes, staking/validator management and snapshots, NFT collection/reserve/stake/receipt operations, external permit services, bridge and checks APIs.
- Add common transaction plans, explicit execution/reporting, fee quotes, registry snapshots, confirmation/state-refresh sequencing and receipt metadata validation.
- Preserve upgraded Stake7/8, distinct NFTStake10, legacy exit routes and explicit unified NFT deposits.
- Add independent calldata/digest fixtures, cross-reviewed negative checks and reproducible operation evidence: 7680 offline checks.

No blockchain transactions were submitted. Source/API acceptance and deployment boundaries are recorded in docs/FINAL_PARITY_0_1_3.md. Existing versioned assets remain unchanged.

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
