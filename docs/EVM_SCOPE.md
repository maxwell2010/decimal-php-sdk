# EVM release scope and evidence

The release helpers build unsigned transactions and perform explicit read/simulation checks. They never submit transactions. A successful builder is not proof that a mutable deployment will remain unchanged until execution.

## Added guarded workflows

- `SafePolicy::read` reads Decimal's actual weighted owners, weight threshold and nonce at one explicit block. `pack` enforces recovered EOA membership, uniqueness and summed weight. `draft` verifies the locally calculated hash against the on-chain `getTransactionHash` at the same block and permits ordinary calls only. This does not substitute signature count for Decimal's weight threshold.
- `VerifiedContract` requires trusted runtime hashes and, for proxies, both implementation address and implementation runtime hash. Every read/draft rechecks the binding at one explicit block selected for that check. This detects changed bindings; it does not pin future network execution.
- `StakingReader` reads authoritative frozen-stake indices at an explicit block. Active means withdrawal type 1, freeze status 1 and nonzero amount. Matured is a separate timestamp condition, not a completed status. Active rows belonging to a different wallet fail. `completeChecked` uses the pinned block timestamp and rejects inactive, completed or immature entries.
- `NftService` checks ownerOf/balanceOf for transfers and builds separate ERC721/ERC1155 approvals before Decimal NFT delegation. Approval steps are not silently executed. Re-read state and wait for successful approval receipts before preparing later steps.
- `DeployedNft` exposes the source-evidenced deployed `mintByDEL` 721/1155 shapes, exact total reserve value, current six-field collection metadata, DEL reserve top-up and unambiguous NFTCreated extraction. The 1155 builder preserves total wei and quantity separately. Reserve-type enum values are never guessed: `addDelReserve` takes the caller's reviewed DEL enum value and checks it against the read reserve and zero token address.
- `TokenService` uses the distinct observed reserveless interface for mint/cap checks. Mint rejects totalSupply + amount above cap; burn rejects amounts above balance. Mint, burn and reserveless creation are simulated before returning a draft, so contract permission/role failures propagate. Reserveless creation is nonpayable in the imported deployed ABI; no guessed native commission is attached.

## Source evidence

Safe policy behavior follows `gosdk/safe_snapshot.go` and `safe_policy.go`. Frozen withdrawal semantics follow `gosdk/workflows.go`. Their profile ABIs and source hashes are already bundled under abi and docs. ERC721/ERC1155 approval/delegation inputs are taken from the imported ERC721, ERC1155 and DelegationNFT artifacts.

The deployed native-reserve NFT shapes match the existing C# `DecimalNftDeployedDrafts.cs`, `DecimalNftReserveReader.cs` and `docs/NFT_ISOLATED_MINT_AUDIT.md`. The reference audit recorded these official testnet compact/verified sources:

- https://testnet-api.decimalchain.com/api/v1/contracts/compact/0xe8e626721423658f569132f18046b212e42e829b
- https://testnet-api.decimalchain.com/api/v1/contracts/compact/0x03794e3da145c580503bbce31215d249c115f93f
- https://testnet-api.decimalchain.com/api/v1/contracts/0xa12ffd3f622e5399274e4ad13032a5cacce02916

The source signature selectors asserted by tests are b125b73f (721 mintByDEL), 759901a3 (1155 mintByDEL), and b307f97f (six-field 721 collection metadata). These are source/call-format checks, not fresh deployment acceptance. This run's direct source refresh timed out; the implementation relies on the already recorded C# evidence and imports.

## Explicit boundaries

- NFT minimum reserve, existing-ID reserve rate and creator/mint permissions remain mutable on-chain rules. The low-level mint builder does not invent a constant minimum. Simulate and use trusted deployment evidence before execution.
- `SafePolicy` retains an ordinary-call EOA shortcut. `SafeSignatures` additionally supports typed EOA, personal-sign EOA, modern bytes32/bytes EIP-1271, legacy bytes/bytes EIP-1271 and prevalidated signatures. Contract interface selection is explicit because magic values/preimages differ. Prevalidated entries require executor == owner or a nonzero pinned approvedHashes value. Delegatecall requires a reviewed target/runtime hash; detected standard proxies additionally require implementation address/runtime evidence, and nonzero value is rejected.
- `VerifiedContract` supports direct deployments and standard EIP-1967 implementation/beacon lookup. Embedded beacon addresses in nonstandard proxy runtime require separate caller evidence; they are not inferred from arbitrary bytecode.
- Economic token conversion/commission orchestration, complete Python workflow DTO/report parity, advanced permit variants and every deployed NFT reset variant are not completed by these new helpers. Existing ABI routes are not labeled as full workflows.
- All observed testnet ABI families still require deployment/network validation. No C# wallet, mnemonic, faucet state or private configuration was accessed.

`tests/evm_release.php` adds focused offline assertions for weighted thresholds, owner/duplicate rejection, pinned policy calls, frozen-stake ownership/maturity/completion, changed bytecode, approval separation, exact deployed NFT selectors/event semantics, reservless cap/balance/simulation behavior and absence of implicit broadcasts.

## Advanced Safe and password checks

`SafeSignatures` follows the Go weighted packer: sorted 65-byte heads with unpadded length-plus-bytes dynamic contract signature tails. Full uint256 owner weights and thresholds are preserved, and total overflow is rejected. Personal-sign signatures recover the prefixed hash and emit v=31/32. All EOA branches reject high-s and invalid scalar values. Contract signatures are checked by an actual read eth_call at the snapshot block before packing, not an optimistic callback.

`ChecksService` now ports official EVM password checks: password keys are keccak256 of exact UTF-8 password bytes; redeem message hash is keccak256(bytes32 checkHash || address redeemer), then Ethereum personal-sign prefix. The nonce read includes explicit `from`, matching the sender-scoped nonces() getter. DEL creation multiplies per-check amount by password count; token creation produces an explicit allowance plan; redeem signs each corresponding check/password. This protocol does not bind the redeem preimage to chain ID or contract address, matching the source rather than inventing incompatible replay protection.

The password helper was verified directly during this run against the pinned official JS source at https://api.bitbucket.org/2.0/repositories/decimalteam/dsc-js-sdk/src/6790d35e2decb0cbb06a9149a9c476c834f99223/src/decimalevm/index.ts. Redeem digests also match the existing C# ChecksService and independent Python eth-account vectors in tests/checks-vectors.json. Public test passwords are not application credentials. Cosmos checks remain a different protocol.
