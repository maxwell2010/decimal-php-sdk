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
| `token.burn` | implemented-workflow | `CurrentTokenEconomy::burn`, `TokenEconomy::burn` | [PARITY_TOKENS_CURRENT](PARITY_TOKENS_CURRENT.md) |
| `token.buy` | implemented-workflow | `CurrentTokenEconomy::buy`, `TokenEconomy::buy` | [PARITY_TOKENS_CURRENT](PARITY_TOKENS_CURRENT.md) |
| `token.convert` | implemented-workflow | `CurrentTokenEconomy::convert`, `TokenEconomy::convert` | [PARITY_TOKENS_CURRENT](PARITY_TOKENS_CURRENT.md) |
| `token.create` | implemented-workflow | `CurrentTokenEconomy::create`, `TokenEconomy::create` | [PARITY_TOKENS_CURRENT](PARITY_TOKENS_CURRENT.md) |
| `token.create_reserveless` | implemented-workflow | `CurrentTokenEconomy::createReserveless`, `TokenEconomy::createReserveless` | [PARITY_TOKENS_CURRENT](PARITY_TOKENS_CURRENT.md) |
| `token.mint` | implemented-workflow | `CurrentTokenEconomy::mint`, `TokenEconomy::mint` | [PARITY_TOKENS_CURRENT](PARITY_TOKENS_CURRENT.md) |
| `token.sell` | implemented-workflow | `CurrentTokenEconomy::sell`, `TokenEconomy::sell` | [PARITY_TOKENS_CURRENT](PARITY_TOKENS_CURRENT.md) |
| `token.update_details` | implemented-workflow | `CurrentTokenEconomy::updateDetails`, `TokenEconomy::updateDetails` | [PARITY_TOKENS_CURRENT](PARITY_TOKENS_CURRENT.md) |
| `nft.add_del_reserve` | implemented-workflow | `NftCurrentOperations::addDelReserve`, `NftOperations::addDelReserve` | [PARITY_NFT](PARITY_NFT.md) |
| `nft.approve` | implemented-workflow | `NftCurrentOperations::approve`, `NftOperations::approve` | [PARITY_NFT](PARITY_NFT.md) |
| `nft.burn` | implemented-workflow | `NftCurrentOperations::burn`, `NftOperations::burn` | [PARITY_NFT](PARITY_NFT.md) |
| `nft.create_collection` | implemented-workflow | `NftCurrentOperations::createCollection`, `NftOperations::createCollection` | [PARITY_NFT](PARITY_NFT.md) |
| `nft.delegate` | implemented-workflow | `NftCurrentOperations::delegate`, `NftOperations::delegate` | [PARITY_NFT](PARITY_NFT.md) |
| `nft.disable_mint` | implemented-workflow | `NftCurrentOperations::disableMint`, `NftOperations::disableMint` | [PARITY_NFT](PARITY_NFT.md) |
| `nft.hold` | implemented-workflow | `NftCurrentOperations::hold`, `NftOperations::hold` | [PARITY_NFT](PARITY_NFT.md) |
| `nft.mint` | implemented-workflow | `NftCurrentOperations::mint`, `NftOperations::mint` | [PARITY_NFT](PARITY_NFT.md) |
| `nft.set_approval_for_all` | implemented-workflow | `NftCurrentOperations::setApprovalForAll`, `NftOperations::setApprovalForAll` | [PARITY_NFT](PARITY_NFT.md) |
| `nft.set_token_uri` | implemented-workflow | `NftCurrentOperations::setTokenUri`, `NftOperations::setTokenUri` | [PARITY_NFT](PARITY_NFT.md) |
| `nft.transfer` | implemented-workflow | `NftCurrentOperations::transfer`, `NftService::transfer721`, `NftService::transfer1155`, `NftOperations::transferFrom` | [PARITY_NFT](PARITY_NFT.md) |
| `nft.transfer_batch_erc1155` | implemented-workflow | `NftCurrentOperations::transferBatch1155`, `NftOperations::transferBatch1155` | [PARITY_NFT](PARITY_NFT.md) |
| `nft.transfer_stake` | implemented-workflow | `NftCurrentOperations::transferStake`, `NftOperations::transferStake` | [PARITY_NFT](PARITY_NFT.md) |
| `nft.withdraw` | implemented-workflow | `NftCurrentOperations::withdraw`, `NftOperations::withdraw` | [PARITY_NFT](PARITY_NFT.md) |
| `checks.create_del` | implemented-workflow | `ChecksService::createDelForSigners`, `ChecksService::createDel` | [PARITY_COMMON](PARITY_COMMON.md) |
| `checks.create_token` | implemented-workflow | `ChecksService::createTokenForSigners`, `ChecksService::createToken` | [PARITY_COMMON](PARITY_COMMON.md) |
| `checks.redeem` | implemented-workflow | `ChecksService::redeemSignatures`, `ChecksService::redeem` | [PARITY_COMMON](PARITY_COMMON.md) |
| `bridge.complete_transfer` | implemented-workflow | `BridgeService::completeTransfer` | [PARITY_COMMON](PARITY_COMMON.md) |
| `bridge.transfer_native` | implemented-workflow | `BridgeService::transferNative` | [PARITY_COMMON](PARITY_COMMON.md) |
| `bridge.transfer_token` | implemented-workflow | `BridgeService::transferToken` | [PARITY_COMMON](PARITY_COMMON.md) |

## Additional current requests

The newer recovery-014 snapshot adds request variants and operations beyond the original 55-entry catalog. Current-profile factories select these semantics; legacy/raw APIs remain explicit. See [current token behavior](PARITY_TOKENS_CURRENT.md), [current staking/validators](PARITY_STAKING_CURRENT.md), [NFT](PARITY_NFT.md) and [current acceptance](CURRENT_PARITY_0_1_4.md).

| Additional request/capability | PHP API |
| --- | --- |
| `decimal.complete_stake` | `StakingService::plan` |
| `decimal.apply_stake_penalty` | `StakingService::plan` |
| `decimal.apply_stake_penalties` | `StakingService::plan` |
| `decimal.add_validator_del` | `ValidatorService::operation` |
| `decimal.add_validator_token` | `ValidatorService::operation` |
| `decimal.remove_validator` | `ValidatorService::operation` |
| `decimal.update_validator_metadata` | `ValidatorService::operation` |
| `nft.stake_to_hold` | `NftLegacyStaking::plan` |
| `nft.reset_stake_hold` | `NftLegacyStaking::plan` |
| `nft.reset_stake_holds` | `NftLegacyStaking::plan` |
| `nft.withdraw_with_reset` | `NftLegacyStaking::plan` |
| `nft.transfer_with_reset` | `NftLegacyStaking::plan` |
| `nft.hold_with_reset` | `NftLegacyStaking::plan` |
| `nft.complete_stake` | `NftLegacyStaking::plan` |
| `token.convert_to_del` | `CurrentTokenEconomy::convertToDel` |
| `token.properties` | `CurrentTokenEconomy::properties` |
| `token.update_min_supply` | `CurrentTokenEconomy::updateMinSupply` |
| `tx.burn_del` | `TransactionExecutor::burnDel` |
| `tx.refresh_result` | `Client::refreshResult` |
| `checks.created_check_hashes` | `ChecksService::createdCheckHashes` |
| `checks.sign_redemption` | `ChecksService::signRedemption` |

Detailed C#/Go read, permit, quote, management, receipt and DTO correspondence is recorded in [staking](PARITY_STAKING.md), [tokens](PARITY_TOKENS.md), [NFT](PARITY_NFT.md) and [common orchestration](PARITY_COMMON.md). Source hashes and original request locations remain in the JSON companion and linked manifests.

The upgraded mainnet stake format, original legacy exits and separate unified NFTStake tuple10 remain supported. Unsupported deployment ABIs, stale nonces/domains, insufficient balances, failed approvals, malformed known events and unavailable reads fail explicitly. Receipt availability and successful simulation do not establish cross-chain settlement or funded NFT lifecycle acceptance.
