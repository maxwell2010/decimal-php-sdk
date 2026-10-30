# Transaction operation parity

All 55 catalog entries now have named request-level implementations or an explicit source-matched safety rejection. The table records 54 implemented workflows and one intentionally disabled generic Multicall ERC20 spender path. This is a source/API acceptance statement, not funded-network acceptance.

Each implementation is backed by independent calldata/digest fixtures, injected state and negative checks. Common TransactionPlan/TransactionExecutor handles prepare/sign/report and explicit broadcast/receipt processing. Approval prerequisites are confirmed before a state refresh and dependent action preparation. No blockchain transactions were submitted.

| Python operation | State | PHP API | Evidence |
| --- | --- | --- | --- |
| `tx.approve_erc20` | implemented-workflow | `Erc20Service::approve` | [parity-tokens](parity-tokens.json) |
| `tx.send_del` | implemented-workflow | `TransactionDraft::del` | [PARITY_COMMON](PARITY_COMMON.md) |
| `tx.send_erc20` | implemented-workflow | `Erc20Service::transfer` | [parity-tokens](parity-tokens.json) |
| `tx.transfer_from_erc20` | implemented-workflow | `Erc20Service::transferFrom` | [parity-tokens](parity-tokens.json) |
| `decimal.delegate_del` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.delegate_erc20` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.hold_del` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.hold_erc20` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.hold_stake_with_reset` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.multisend_del` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.multisend_erc20` | disabled-unsafe-reference | `StakingService::plan`, `RestrictedMultisend::plan`, `RestrictedMultisend::withPermit` | [parity-staking](parity-staking.json) |
| `decimal.pause_self_validator` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.pause_validator` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.reset_stake_hold` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.stake_token_to_hold` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.transfer_del_stake_with_reset` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.transfer_stake_del` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.transfer_stake_erc20` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.transfer_stake_with_reset` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.unbond_del` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.unbond_erc20` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.unpause_self_validator` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.unpause_validator` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.withdraw_del_stake_with_reset` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.withdraw_hold_del` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.withdraw_hold_erc20` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `decimal.withdraw_stake_with_reset` | implemented-workflow | `StakingService::plan` | [parity-staking](parity-staking.json) |
| `token.burn` | implemented-workflow | `TokenEconomy::burn` | [parity-tokens](parity-tokens.json) |
| `token.buy` | implemented-workflow | `TokenEconomy::buy` | [parity-tokens](parity-tokens.json) |
| `token.convert` | implemented-workflow | `TokenEconomy::convert` | [parity-tokens](parity-tokens.json) |
| `token.create` | implemented-workflow | `TokenEconomy::create` | [parity-tokens](parity-tokens.json) |
| `token.create_reserveless` | implemented-workflow | `TokenEconomy::createReserveless` | [parity-tokens](parity-tokens.json) |
| `token.mint` | implemented-workflow | `TokenEconomy::mint` | [parity-tokens](parity-tokens.json) |
| `token.sell` | implemented-workflow | `TokenEconomy::sell` | [parity-tokens](parity-tokens.json) |
| `token.update_details` | implemented-workflow | `TokenEconomy::updateDetails` | [parity-tokens](parity-tokens.json) |
| `nft.add_del_reserve` | implemented-workflow | `NftOperations::addDelReserve` | [parity-nft](parity-nft.json) |
| `nft.approve` | implemented-workflow | `NftOperations::approve` | [parity-nft](parity-nft.json) |
| `nft.burn` | implemented-workflow | `NftOperations::burn` | [parity-nft](parity-nft.json) |
| `nft.create_collection` | implemented-workflow | `NftOperations::createCollection` | [parity-nft](parity-nft.json) |
| `nft.delegate` | implemented-workflow | `NftOperations::delegate` | [parity-nft](parity-nft.json) |
| `nft.disable_mint` | implemented-workflow | `NftOperations::disableMint` | [parity-nft](parity-nft.json) |
| `nft.hold` | implemented-workflow | `NftOperations::hold` | [parity-nft](parity-nft.json) |
| `nft.mint` | implemented-workflow | `NftOperations::mint` | [parity-nft](parity-nft.json) |
| `nft.set_approval_for_all` | implemented-workflow | `NftOperations::setApprovalForAll` | [parity-nft](parity-nft.json) |
| `nft.set_token_uri` | implemented-workflow | `NftOperations::setTokenUri` | [parity-nft](parity-nft.json) |
| `nft.transfer` | implemented-workflow | `NftService::transfer721`, `NftService::transfer1155`, `NftOperations::transferFrom` | [PARITY_COMMON](PARITY_COMMON.md) |
| `nft.transfer_batch_erc1155` | implemented-workflow | `NftOperations::transferBatch1155` | [parity-nft](parity-nft.json) |
| `nft.transfer_stake` | implemented-workflow | `NftOperations::transferStake` | [parity-nft](parity-nft.json) |
| `nft.withdraw` | implemented-workflow | `NftOperations::withdraw` | [parity-nft](parity-nft.json) |
| `checks.create_del` | implemented-workflow | `ChecksService::createDelForSigners`, `ChecksService::createDel` | [PARITY_COMMON](PARITY_COMMON.md) |
| `checks.create_token` | implemented-workflow | `ChecksService::createTokenForSigners`, `ChecksService::createToken` | [PARITY_COMMON](PARITY_COMMON.md) |
| `checks.redeem` | implemented-workflow | `ChecksService::redeemSignatures`, `ChecksService::redeem` | [PARITY_COMMON](PARITY_COMMON.md) |
| `bridge.complete_transfer` | implemented-workflow | `BridgeService::completeTransfer` | [PARITY_COMMON](PARITY_COMMON.md) |
| `bridge.transfer_native` | implemented-workflow | `BridgeService::transferNative` | [PARITY_COMMON](PARITY_COMMON.md) |
| `bridge.transfer_token` | implemented-workflow | `BridgeService::transferToken` | [PARITY_COMMON](PARITY_COMMON.md) |

Detailed C#/Go read, permit, quote, management, receipt and DTO correspondence is recorded in [staking](PARITY_STAKING.md), [tokens](PARITY_TOKENS.md), [NFT](PARITY_NFT.md) and [common orchestration](PARITY_COMMON.md). Source hashes and original request locations remain in the JSON companion and linked manifests.

The upgraded mainnet stake format, original legacy exits and separate unified NFTStake tuple10 remain supported. Unsupported deployment ABIs, stale nonces/domains, insufficient balances, failed approvals, malformed known events and unavailable reads fail explicitly. Receipt availability and successful simulation do not establish cross-chain settlement or funded NFT lifecycle acceptance.
