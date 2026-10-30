# SDK coverage and compatibility

Release 0.1.3 covers the source-evidenced SDK interfaces listed below. ABI bindings, complete guarded workflows, stateless node validation and live execution are separate evidence levels. Function/schema counts are not a parity percentage or distinct transaction count.

| Area | Released implementation and checks | Boundary |
| --- | --- | --- |
| Contract ABI | 28 profiles and 654 typed bindings; original 577 bindings have independent Python calldata/decode fixtures, with dedicated upgrade regressions for current staking | Select an ABI matching the actual deployment; source URLs and hashes are recorded separately from packaged normalized files |
| Wallet and EVM envelopes | English BIP39/BIP32/NFKD, address sequences, exact units, EIP-55, EIP-155/2930/1559, contract creation and sender/hash recovery | Other mnemonic languages require explicit future wordlists; never infer a legacy address hash from an EVM key |
| Fees and signing | Buffered gas reserve versus effective estimate, explicit dynamic caps, callback/hardware signer adapter with exact unsigned-hash binding, cancellation between bounded operations | External signer transport is supplied by the application; unknown dynamic base fee is represented by null estimate |
| Nonce coordination | Explicit locked reservations with fsynced snapshots and atomic replace, corrupt-state rejection, 100 cross-process reservations tested | Reserved nonces are not automatically released; network filesystems and recovery after lost storage require application reconciliation |
| Native/ERC20/NFT | Transfers, memo, ABI operations, allowance/reset plans, NFT owner/balance checks, separate delegation approvals, deployed native-reserve mint/create and unambiguous creation events | Approval steps require confirmed success and state refresh; deployment/role/economic checks remain mutable |
| Staking | StakingService request variants, regular/held/frozen snapshots, DTOs and pinned completion builders | Indexer-derived indices require freshness/authority validation; node state may change before execution |
| Token economy, bridge, validators | Named economics/bridge/validator services, reserve quotes, managed approval/permit plans and receipt records | TokenEconomy, StakingService, ValidatorService and common TransactionPlan/Executor cover source-level orchestration; deployment state remains authoritative |
| Checks | Official JS-compatible password key and redeem digest, sender-scoped nonce, native value multiplication, token allowance plan and redemption | Tokens/centers must match selected source ABI; password signatures are authorization material |
| Safe | Weighted pinned-block policy, typed/personal EOA signatures, explicit modern/legacy EIP-1271 validation, prevalidated approvals, sorted dynamic signature tails and reviewed delegatecall guards | Signatures must be checked against current policy/deployment before execution; caller-reviewed trust evidence is mandatory for delegatecall |
| Deployments/events | Runtime and implementation runtime hashes, EIP-1967/beacon checks, pinned verified reads, ABI event decoding | Nonstandard embedded-beacon proxies need explicit evidence rather than guessed bytecode inference |
| RPC/API | Bounded strict JSON-RPC, chain-checked read-only failover, no replay of ambiguous writes, read-only API/indexer paths and lossless large integers | SDK returns schema/raw payloads where no stable response DTO exists; app-specific Python telemetry and bot orchestration are not PHP transport APIs |
| WebSocket | TLS handshake, bounded frames/queues, ping/pong, fragmentation, interleaved notifications, explicit reconnect/resubscribe preserving definitions after failure | Blocking interface, explicit remote-ID remap, no silent retries or async socket multiplexing |
| Cosmos schemas | 393 descriptor-derived schemas, encode/decode, correct proto3 default packing, unknown/duplicate/oneof/range checks and independent repeated-field fixtures | Strict versioned decoding can reject future unknown fields; reimport reviewed descriptors before accepting a new schema |
| Cosmos validation/signing | All 43 pinned ValidateBasic branches, 129 actual-node corpus cases and boundaries; required signer extraction/order, checked/validated signing, envelope checks and exact Go reference bytes | Stateless validation does not establish governance/oracle roles, ante admission, account balances or successful execution |
| Cosmos fees/transport | Signed-byte fixed-point convergence with cap/snapshot/cycle checks, native HTTP/2 unary queries and simulation over 56 descriptor-backed read routes | gRPC write routes are rejected; fee callbacks must pin transaction identity/parameters; no live provider acceptance campaign |
| Distribution | Stable latest ZIP, versioned ZIP, Composer repository metadata, checksums, lockfile, examples, tests and gated release workflow | Packagist is not required; future compatible dependency updates are explicit through Composer |

No blockchain transaction has been submitted during this release's development. Read/simulation capability and offline parity do not imply funded-wallet execution acceptance. This is the actual released scope, not a claim that every application-specific helper in every language has an identical PHP class.

Further evidence: [EVM_SCOPE.md](EVM_SCOPE.md), [COSMOS_VALIDATION.md](COSMOS_VALIDATION.md), source manifests and tests.

## Dependency advisory resolution

The old web3.php/ethereum-util dependency chain required phpseclib 2.x and triggered PKSA-12sz-bcny-pk4m. Those packages and phpseclib were removed; no audit exceptions or replacement packages hide the advisory. The SDK owns its strict ABI/RLP codecs and uses the reviewed secp256k1 dependency directly. RLP leading-zero byte preservation, independent signature recovery and malformed-envelope regression tests cover the replacement paths.

## Operation-level acceptance

The 0.1.3 release closes the remaining named operation gaps. [AUDIT_OPERATION_PARITY.md](AUDIT_OPERATION_PARITY.md) records 54 implemented request workflows and one intentionally disabled unsafe generic Multicall ERC20 spender path, with the verified sender-restricted alternative retained. The JSON companion preserves exact source evidence and links each operation to its implementation/tests.

Additional Go/C# quote/read/permit/management/receipt/DTO mappings are documented in [FINAL_PARITY_0_1_3.md](FINAL_PARITY_0_1_3.md) and the domain reports. Common TransactionPlan/TransactionExecutor connects service drafts to preparation, signing, opt-in submission and receipt reporting. Approval-dependent actions require confirmed prerequisites and a state refresh. Language-specific API class names, runtime scheduling and application telemetry are not the acceptance unit.

Earlier fixes remain included: [0.1.1 audit](FINAL_AUDIT_0_1_1.md) and [0.1.2 staking upgrade](STAKING_COMPATIBILITY_0_1_2.md). Network execution/settlement acceptance remains distinct from source/API parity; no transactions were submitted.
