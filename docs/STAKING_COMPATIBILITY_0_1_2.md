# Decimal staking compatibility in 0.1.2

This patch extends the independent [0.1.0 final audit](FINAL_AUDIT_0_1_1.md). No blockchain transactions, deployment or history replay were performed.

## Pinned upgrade evidence

The node upgrade at mainnet block 34342701 is pinned to [go-smart-node a46cb48d7965c1a64a495cebb22e735dfe4b9da3](https://github.com/decimalteam/go-smart-node/commit/a46cb48d7965c1a64a495cebb22e735dfe4b9da3). Its [delegation binding](https://github.com/decimalteam/go-smart-node/blob/a46cb48d7965c1a64a495cebb22e735dfe4b9da3/contracts/delegation/DecimalDelegation.go) defines the updated stake tuple:

| Index | Field | Solidity type |
| --- | --- | --- |
| 0 | validator | address |
| 1 | delegator | address |
| 2 | token | address |
| 3 | amount | uint256 |
| 4 | tokenId | uint256 |
| 5 | tokenType | uint8 |
| 6 | holdTimestamp | uint256 |
| 7 | holdStartTime | uint256 |

`getStakes(bytes32[])`, its selector and the RPC method `eth_call` remain unchanged. holdStartTime is the hold start time, not an amount. There is no new division by 1000 or replacement of failed reads with zero. PHP integers from ABI remain decimal strings.

## Compatibility boundaries

Packaged stake output schemas support the new tuple. The explicitly recognized Decimal stake structure also accepts canonical legacy seven-field outputs. Both paths retain strict ABI offset, count, size, address padding and integer-width validation. Other ABI structures do not gain a generic lenient fallback. Applications must inspect whether the optional eighth field is actually present; no start time is invented for a legacy position.

The change covers direct contract decoding, generated contract reads and nested frozen stake reads. Generic raw RPC/indexer payloads remain application-owned. The SDK has no persistent stake valuation or validator-display cache to invalidate.

## Events and NFT routing

Stake event tuple changes alter their signature/topic0. [Legacy event hooks](https://github.com/decimalteam/go-smart-node/blob/a46cb48d7965c1a64a495cebb22e735dfe4b9da3/x/validator/keeper/evm_hooks_legacy.go) normalize events inside the node; raw receipt logs still require the matching original event ABI. Do not reinterpret an old topic using the new tuple.

New deposits into legacy DelegationNFT are blocked on the upgraded network, while exits from existing positions remain permitted. A new-deposit route or NFT approval must not globally remap old positions to a different deployment. ERC721 ownerOf is not an ERC1155 balance check. Existing tokenId/amount pairing must be preserved for NFT multi-exit operations.

`NftService::delegate721` and `delegate1155` accept an appended optional `profile` argument. Explicit `Delegation20261030` selects the unified `delegateNFT` / `delegateNFT1155` selectors and approvals to the supplied deployment. The default legacy profile is retained for existing testnet callers; on configured mainnet chain 75 it fails before RPC reads or producing approvals. Callers must supply the actual trusted unified deployment address. Low-level ABI builders remain available for historical/exit workflows; they do not detect deployment state or bypass node admission rules.

`Events::decode` and `Events::receipt` recognize the exact seven/eight-field Stake event variants by their distinct topic0, validate the actual payload canonically, and preserve its original signature and field count. No node hook normalization is applied to receipt data.

## Validation

The combined offline suite passes 6981 checks. Upgrade regressions cover multiple positions, empty arrays, nested frozen arrays, legacy custom ABI, generated reads, missing legacy holdStartTime, malformed offsets/counts/lengths/padding, both event signatures and NFT route/approval selection. Provenance and packaged hashes are in [stake-upgrade-sources.json](stake-upgrade-sources.json). The package contains 28 ABI profiles and 654 generated methods.

The broader [operation parity matrix](AUDIT_OPERATION_PARITY.md) still records ABI-only and partial high-level workflows explicitly. Offline compatibility tests do not establish mutable deployment roles, live provider freshness or execution admission.
