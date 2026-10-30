# PHP SDK 0.1.3 parity acceptance

Three implementation agents covered staking/validators, tokens/economics and NFTs. The parent implemented bridge/checks and common preparation/reporting. Agents then cross-reviewed each other's changes. No blockchain transaction, approval, deployment or funded replay was performed. Signing checks use public deterministic test keys and injected RPC only.

## Closed matrix gaps

[All 55 catalog entries](AUDIT_OPERATION_PARITY.md) now have named PHP request-level mappings and linked tests. There are 54 implemented workflows and one intentionally rejected unsafe generic ERC20 Multicall spender path. The latter matches the maintained Python/Go policy; a recovery Python snapshot differs by retaining an unsafe allowance route. PHP retains the verified sender-restricted RestrictedMultisend alternative. Unsafe behavior is not silently reintroduced or described as an unimplemented operation.

- ERC20 approval/transfer/transferFrom, token buy/sell/convert/create/reserveless/mint/burn/details, reserve queries and all exact-input/output trade variants.
- Decimal delegation/hold/reset/unbond/transfer/withdraw variants, validator pause and management, snapshots, frozen records, completion predicates and receipt extraction.
- NFT creation/mint/burn/approval/batch/URI/reserve/disableMint, legacy/unified hold/delegate/exits, independent ten-field NFTStake reads, and ordered movement/URI/creation reports.
- ERC20 and distinct DRC721/DRC1155 permit preparation/verification, external signer inputs, nonce/domain/owner checks and scalar bounds.
- Bridge native/token/completion and fee quotes; checks creation by address/password, external redemption signatures, sender-scoped nonce and created/redeemed reports.
- Common TransactionPlan/TransactionExecutor, FeeQuote and TransactionResult with exact fee/receipt quantities, registry snapshots, cancellation and explicit submission defaults.

The linked [staking](PARITY_STAKING.md), [tokens](PARITY_TOKENS.md), [NFT](PARITY_NFT.md) and [common](PARITY_COMMON.md) reports enumerate C#/Go convenience reads, permit APIs, records and workflows beyond the original Python catalog. Language-specific class names and synchronous/asynchronous runtime models are not copied as API requirements. Original source locations/hashes and the new implementation evidence remain in the JSON manifests.

## Review findings fixed

Cross-review corrected token Unicode strip/commission handling, strengthened independent trade argument-order fixtures, restored the exact source legacy resetHoldDEL selector, bounded directly constructed stake tokenType, and improved missing request-field errors. The current ABI does not advertise resetHoldDEL; its explicit legacy compatibility call fails simulation if the supplied deployment lacks it. An explicit wrapped-token reset is available for the canonical current interface.

Executor regressions cover cancellation after signing, failed approval receipts, exact sender/unsigned-hash binding, changed action after state refresh, and invalid event profile rejection before prerequisites. Receipt decoding rejects removed known events and transaction/block metadata mismatches. NFT decoding filters foreign emitters before sorting and preserves repeated ID/amount entries.

## Acceptance and boundaries

The combined PHP suite passes 7680 checks. It retains the previous independent ABI/signature/Cosmos corpora, including 2119 protobuf field-wire fixtures across 393 descriptors, and adds independent eth_abi/keccak fixtures plus injected state/error/approval sequencing checks. The parity catalog is generated reproducibly and CI checks it against committed manifests. Installation tests run both offline examples and the entire installed-package suite.

Deployment addresses/profiles and mutable node state remain explicit. Stake7/8 and nested frozen records retain the original meanings of holdTimestamp and holdStartTime. Unified NFTStake remains a separate tuple10. Legacy position reads/exits are preserved; new mainnet NFT deposits require explicit unified selection. The 0.1.2 real mainnet fixture decoding evidence remains valid and was not replaced by a new paid/live campaign.

Source/API parity is the acceptance scope. Funded transaction admission, current roles/balances, cross-chain settlement, external attestation authenticity and a funded NFT lifecycle are not established by offline tests. Errors and unavailable values are not replaced with zeros. SDK submission remains opt-in; none was performed during this release.

## Distribution

Release 0.1.3 preserves the permanent latest Composer metadata and ZIP URLs. Versioned archives remain immutable. CI gates publication on Windows/Linux/macOS with PHP 8.2-8.5. Composer audit remains a separate required check without advisory suppression. See CHANGELOG.md and RELEASE_NOTES.md for install instructions and a runnable offline example.
