# Current Python staking and validator delta

This review compares the recovery Python snapshot sdk-release-014 with PHP 0.1.3, extending the original operation catalog. Source paths and SHA256 hashes are pinned in parity-staking-current.json.

- StakingService::plan adds complete_stake with positional, nonempty, unique uint index lists. These are frozen stake indices, not hold timestamps.
- apply_stake_penalty and apply_stake_penalties require allow_legacy=true and encode their distinct historical applyPenaltyToStake/applyPenaltiesToStake selectors. All three source addresses must be nonzero. The current canonical ABI does not advertise these selectors; explicit legacy opt-in does not guarantee deployment support. No new mainnet route is inferred.
- ValidatorMetadata implements structured operator/reward address, consensus pubkey, description and exact commission-string validation. Description fields and compact UTF-8 JSON field ordering match the Python wire format. Commission accepts finite decimal/scientific exact strings in [0,100], preserving the original string in metadata.
- ValidatorService::operation adds add_validator_token/del, remove_validator and update_validator_metadata. The Python add_validator names encode addCandidate/addCandidateDEL: they do not bypass owner approval. These convenience requests add no implicit token approvals.
- ERC20 staking auto_approve now defaults to false, matching the current Python request dataclasses. Explicit true produces reviewed prerequisite drafts. Permit signing remains explicit rather than loading secrets from request dictionaries.
- withPermitSignature accepts an external signature for delegate or delegateHold, reconstructs and checks the token domain/current nonce/spender/owner/value/deadline, verifies the signature, checks balance and simulates the resulting action. A changed value or stale/wrong signature fails rather than falling back silently.

All earlier staking request fields (decimal units, holds, transfers and reset lists) were compared with current Python decimal.py. Validator status/active/member queries are supplied by ValidatorService::read. Stake/frozen DTOs, pinned snapshots and withdrawal receipts remain covered by the preceding release. Generic Multicall ERC20 approval remains an intentional safety deviation; the separately verified RestrictedMultisend covers supported batching.

Tests in staking-current-parity.php cover structured metadata, exact candidate calldata/value, commission boundaries/scientific values, completion and legacy opt-in selectors, default approval refusal, and externally signed permit delegate/hold binding. RPC is injected and signatures use a public deterministic fixture key; no blockchain transaction, funded validation or deployment is performed.
