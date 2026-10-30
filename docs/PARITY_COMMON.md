# Common transaction, bridge and checks parity

The pinned Python/C# sources and their hashes are recorded in [parity-common.json](parity-common.json). The original Go ABI/signing corpus remains packaged and tested. Public APIs use PHP services and immutable drafts/records rather than copying language-specific class names.

| Reference capability | PHP implementation |
| --- | --- |
| Native transfer/memo and burn | TransactionDraft::del / burnDel |
| Fee estimate, reserved gas, affordability | Client::estimateFee and FeeQuote; exact and buffered estimates do not reserve a nonce |
| Prepare, signer binding, explicit broadcast, receipt | TransactionExecutor plus the existing Client/Signer/PreparedTransaction/SignedTransaction APIs |
| Approval prerequisites | TransactionPlan and executePlan; no dependent action is pre-signed before confirmed approvals and state refresh |
| Receipt/fee DTO | TransactionResult, lossless decimal quantities, dry_run/pending/success/failed status, actual paid fee, original receipt and optional operation metadata |
| Registry resolution | Client::resolveSystemContract / resolveSystemContracts, chain check, zero-address rejection and one concrete block snapshot |
| Bridge native/token/completion | BridgeService, source uint16 chain ID, EVM recipient padded to bytes32, native amount+fee versus token fee-only value |
| Bridge minimum fee / quote | BridgeService::minimumServiceFee / quote, explicit snapshot and overflow checks |
| Checks address/password creation | ChecksService::createDelForSigners / createTokenForSigners plus compatible password helpers |
| Checks sender-scoped nonce | ChecksService::nonce, eth_call explicitly includes creator from |
| Checks external/password redemption | ChecksService::redeemSignatures / redeem, canonical r/s/v and source EIP-191 preimage |
| Checks owner/implementation/receipts | ChecksService::owner / implementation / createdChecks / redeemedChecks |

## Sequencing and defaults

Services return unsigned drafts or explicit approval/action plans. `TransactionExecutor::execute` defaults to no broadcast. A dry run can prepare and sign using an explicitly supplied Signer; it does not imply submission. Fee options preserve the existing legacy/access-list/dynamic-fee behavior, fee caps, cancellation and optional process-safe nonce manager.

For a plan with pending approvals, nonbroadcast execution returns `requires_approvals` without trying to simulate or sign the dependent action. Broadcasting a plan requires an explicit refresh callback. Each approval is prepared against the current nonce, signed, submitted and confirmed successfully before continuing. A reverted approval stops execution. The callback then rebuilds the plan from fresh state; a remaining approval or changed action requires a new review instead of signing an unexpected action. Invalid event profiles are rejected before any prerequisite submission. This sequencing is tested only through injected local RPC.

`TransactionResult::fromReceipt` and Client::transactionResult support existing transaction hashes without access to a private key or original preparation. Missing receipts remain pending. A receipt must match the exact requested hash and contain a canonical status. Gas/price zero stays zero; unavailable fields stay null. Paid and reserved fee amounts are separate. Token address/hold/token balance details can be supplied as operation metadata from service readers; they are never invented from unavailable chain data.

## Deployment and evidence boundaries

Bridge-v2 is an explicit deployment ABI, not automatic settlement or proof generation. The supplied VM must come from an authentic external attestation workflow. Token auto-approval is optional and returns a prerequisite plan. Service-fee quotes do not establish route availability or destination-chain completion.

Checks support direct signer addresses and externally generated redemption signatures as well as the existing password workflow. Permit values must fit the exact ABI and signature scalars. Nonces are creator-scoped; competing creates may invalidate a prepared action. Receipt lists preserve duplicate records and filter emitter addresses.

Known events retain strict payload/topic decoding. Removed logs and transaction/block metadata mismatches reject; unrelated emitters do not become matching events. Old/new stake topic0 values remain distinct and receipt data is not normalized in place.

Tests use independent eth_abi/eth_utils fixtures for bridge/checks and injected RPC for execution, cancellation, failed approvals, nonce sequencing, action refresh identity, registry snapshots and report fields. Deterministic public test keys are used only offline. No actual approvals, transfers, deployment or other blockchain transaction were submitted.
