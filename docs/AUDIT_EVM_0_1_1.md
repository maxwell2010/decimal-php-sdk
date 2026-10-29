# EVM audit for 0.1.1

Read-only comparison used the current local Go/Python/C# source references. No blockchain transactions or live state mutations were performed.

## Confirmed fixes

1. **Proxy binding could omit implementation evidence.** In 0.1.0, `VerifiedContract` accepted only the stable runtime hash of a standard proxy. Its implementation could change while the proxy bytecode hash remained unchanged, and verification would still pass. The verifier now discovers standard EIP-1967 implementation/beacon bindings and rejects omitted implementation trust evidence. Existing explicit implementation bindings still verify both address and runtime at the pinned block. Regression tests cover missing evidence, changed implementation and changed implementation runtime.
2. **EIP-1271 validation used a different caller from execution.** `SafeSignatures::pack` previously omitted `from` from its owner-contract eth_call. A contract owner whose signature policy depends on msg.sender could be falsely rejected or optimistically accepted in the wrong context. Calls now explicitly use the Safe address as `from`, preserving the caller seen when the Safe invokes an owner contract. Tests cover both explicitly selected modern and legacy signature interfaces, and require the correct caller/block.
3. **Circular standard proxy addresses were returned as valid evidence.** `Deployment::implementation` now rejects an implementation equal to the proxy, a self-referencing beacon, and zero/self implementation results from a beacon. This follows the explicit missing/circular-address rejection in the Go `proxyEvidence` path.

4. **Beacon lookup used the wrong caller context.** The implementation() eth_call now sets from=proxy, matching the external caller seen by a beacon during a proxy lookup. A regression fixture requires that caller rather than permitting a zero-address lookup to attest another implementation.

## Evidence and test boundary

Go `gosdk/proxy.go` defines the EIP-1967 slots, requires canonical address words and rejects missing/circular implementations. PHP retains canonical-word verification through `AbiCodec` and now also rejects circular pointers. Go weighted signature policy explicitly requires contract signature validation rather than treating opaque bytes as EOA signatures. C# `SafeSignature` similarly distinguishes opaque owner-contract payloads. The EIP-1271 caller correction follows the actual EVM external-call context of a Safe calling an owner contract; tests model owner authorization requiring the Safe caller.

Run `tests/audit-evm.php` through the shared test harness. It uses injected RPC responses and public test addresses only. Existing `tests/evm_release.php` direct-deployment mock was updated to provide canonical zero EIP-1967 slots.

## Remaining scope boundaries

These fixes do not pin future execution state. Application-specific economic orchestration and every deployed NFT reset workflow are still not interchangeable with generic ABI coverage. Contract-signature interface selection remains explicit and must match the intended Safe/owner deployment. Nonstandard proxies with embedded beacon addresses are not inferred from arbitrary bytecode. Python/C# report DTOs and auto-execution helpers do not become PHP API parity merely because matching calldata can be built. No full deployed business parity or funded-wallet acceptance is claimed by this audit.
## Added bounded workflow parity

`RestrictedMultisend` ports `gosdk/restricted_multisend.go` sendToken and sendTokenWithPermit interfaces. It requires caller-reviewed network/runtime evidence, rechecks standard proxy implementation evidence, validates 1..500 positive recipients and a UTF-8 memo up to 256 bytes, rejects owner/contract/zero recipients and uint256 total overflow, checks balance and returns separate allowance/reset steps. Permit plans use checkedPermit's actual nonce/domain verification and authorize exactly the summed amount to the reviewed deployment. It never grants allowance to generic Multicall. Independent eth-abi/eth-account vectors verify ordinary and permit calldata. Mutable deployment evidence still needs refresh before execution.

`NftService::setTokenUri` uses the already bundled exact setTokenURI(uint256,string) functions in PythonErc721/PythonErc1155, restricts profile selection and simulates the unsigned call so permission failures propagate. Those functions are not present in every observed deployed profile; no universal deployed support is inferred. An earlier draft of the operation matrix incorrectly called this ABI absent; the final matrix corrects that factual error.

`docs/audit-operation-parity.json` and `docs/AUDIT_OPERATION_PARITY.md` enumerate all 55 Python catalog operations, concrete signature candidates, Go route evidence and C# source occurrences. They deliberately distinguish ABI builders from complete workflows. General token-economy, reset and bridge orchestration/DTO parity remains explicitly partial.
