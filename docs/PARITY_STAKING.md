# Staking operation parity

`StakingService::plan(operation, sender, request, block)` covers the 21 staking/validator operations in the Python operation catalog plus native multisend. Requests use Python snake_case field names, human decimal amounts as strings, optional integer decimals, and uint timestamp strings. The returned approvals/action plan feeds the shared transaction preparation/signing/receipt workflow. No plan sends a transaction.

Constructor arguments are Client, delegation address, wrapped DEL address, master validator address, and optional explicit ABI profile. The default Delegation preserves existing positions; Delegation20261030 must be selected explicitly for a current deployment. Changing a profile never changes a supplied deployment address.

Amounts use exact base-10 parsing. DEL uses 18 decimals, ERC20 reads decimals if omitted. Deposits check balance and allowance at the supplied block. Insufficient allowance returns explicit approval steps (optional zero reset); auto_approve=false rejects. The action requires fresh planning after confirmed approvals. No action is simulated as if unconfirmed approvals existed. Already-approved actions and withdrawals/reset/validator methods propagate node simulation failures, including current roles and maturity. ABI validation remains strict.

`withPermit` is the explicit EIP-2612 path using checked token domain, nonce, deadline and wallet binding. Callers choose allowance planning or checked permit rather than silently guessing signer capabilities. A failed permit is not silently treated as approved.

Native multisend retains the Python single-recipient direct transfer and UTF-8 memo behavior; multiple recipients use aggregate with a final zero-value memo entry. The generic ERC20 Multicall spender is intentionally disabled: the recovery Python worktree still enables that unsafe route, while the maintained Python source and Go matrix disable it. Equivalent supported ERC20 batching is provided by the existing runtime/implementation-verified RestrictedMultisend plan and permit methods.

Transfer/reset combined routes remain available, but simulation does not establish future admission. `separateResetTransfer` implements the C# non-atomic fallback: reset then confirm, reread state and reprice before transfer. Never aggregate the steps; later failure does not roll back a reset. Duplicate reset timestamps are rejected for this fallback, matching C#.

## Evidence

`parity-staking.json` pins source snapshots and per-operation Python line numbers. `staking-parity-vectors.json` contains 21 calldata/value examples independently encoded by Python eth_abi/eth_utils. Tests compare exact payload/value/target, allowance/reset requirements, missing approvals, insufficient balances, decimal discovery/precision, node role rejection, memo routing and separate reset/transfer state boundaries. Injected RPC rejects unknown methods, including broadcasts.

No funded-wallet acceptance, live transaction or deployment is part of this validation. DTO layouts and exception wrapping differ by language; request arguments, units, variants and explicit state transitions are preserved.

## Reads, records and maintenance

StakePosition mirrors Python/C# stake records with exact 7/8-field preservation, exists/isHold/matured and formatted amount. StakingReader adds single regular/held stake reads, snapshot totals and missing holds pinned to one concrete block, frozen position/batch reads (128 maximum, preserving order), penalty drafts, and withdrawal receipt extraction. Withdrawal logs must match emitter and actual old/new topic; removed logs and transaction/block metadata mismatches reject. FrozenStake also exposes transfer activity and completion-time predicates. A zero amount is only an actual decoded missing position, never a replacement for failed RPC/decode.

ValidatorService::read supports status, active, member, validator record, penalty list, reward+enabled, owner and implementation. Constructor takes an explicit address; resolved() obtains master-validator via ContractCenter with the existing chain checks. draft() covers C# candidate/validator DEL/token additions, owner-bound batches/approvals/penalties, removal, metadata and pause variants. Initial token stakes are raw uint strings; DEL is human decimal. Owner binding is caller-supplied offline evidence exactly as in C#, not a guarantee of current authority. Node simulation and final admission remain authoritative. StakingService::plan provides simulated pause operations when desired.

All matching C# source files are pinned in the machine manifest. Tests/staking-reads-parity.php exercises snapshot pinning, record formatting, validator fields, draft value/owner guards, actual event tuple decoding and receipt metadata rejection.

The Python token-less reset request uses the exact resetHoldDEL(address,address,uint256) selector via its source-specific legacy interface. It is not silently rewritten as resetHold with wrapped DEL. Current node ABI profiles do not advertise this legacy selector: if the actual supplied deployment lacks it, simulation fails explicitly. For canonical current contracts, use the explicit token request with the configured wrapped DEL address.
