# Transaction operation audit

All 55 Python catalog entries are enumerated below. The JSON companion records Python source locations/hashes, actual PHP candidate signatures, Go operation names and C# quoted-signature occurrences. Selector matches alone do not establish equivalent approval, economic, permit, profile or receipt semantics. No parity percentage is claimed.

| Python operation | PHP level | Named PHP helper | Candidate ABI signatures |
| --- | --- | --- | --- |
| `tx.approve_erc20` | abi-builder-only |  | 4 |
| `tx.send_del` | direct-native-helper | `TransactionDraft::del` | 0 |
| `tx.send_erc20` | abi-builder-only |  | 4 |
| `tx.transfer_from_erc20` | abi-builder-only |  | 4 |
| `decimal.delegate_del` | abi-builder-only |  | 2 |
| `decimal.delegate_erc20` | abi-builder-only |  | 3 |
| `decimal.hold_del` | abi-builder-only |  | 1 |
| `decimal.hold_erc20` | abi-builder-only |  | 2 |
| `decimal.hold_stake_with_reset` | abi-builder-only |  | 1 |
| `decimal.multisend_del` | partial-checked-helper | `Workflows::multisendDel` | 1 |
| `decimal.multisend_erc20` | partial-checked-helper | `RestrictedMultisend::plan`, `RestrictedMultisend::withPermit` | 2 |
| `decimal.pause_self_validator` | abi-builder-only |  | 2 |
| `decimal.pause_validator` | abi-builder-only |  | 2 |
| `decimal.reset_stake_hold` | abi-builder-only |  | 2 |
| `decimal.stake_token_to_hold` | abi-builder-only |  | 1 |
| `decimal.transfer_del_stake_with_reset` | abi-builder-only |  | 1 |
| `decimal.transfer_stake_del` | abi-builder-only |  | 1 |
| `decimal.transfer_stake_erc20` | abi-builder-only |  | 1 |
| `decimal.transfer_stake_with_reset` | abi-builder-only |  | 1 |
| `decimal.unbond_del` | abi-builder-only |  | 1 |
| `decimal.unbond_erc20` | abi-builder-only |  | 1 |
| `decimal.unpause_self_validator` | abi-builder-only |  | 2 |
| `decimal.unpause_validator` | abi-builder-only |  | 2 |
| `decimal.withdraw_del_stake_with_reset` | abi-builder-only |  | 1 |
| `decimal.withdraw_hold_del` | abi-builder-only |  | 1 |
| `decimal.withdraw_hold_erc20` | abi-builder-only |  | 1 |
| `decimal.withdraw_stake_with_reset` | abi-builder-only |  | 1 |
| `token.burn` | partial-checked-helper | `TokenService::burn` | 3 |
| `token.buy` | abi-builder-only |  | 6 |
| `token.convert` | abi-builder-only |  | 4 |
| `token.create` | abi-builder-only |  | 2 |
| `token.create_reserveless` | partial-checked-helper | `TokenService::createReserveless` | 2 |
| `token.mint` | partial-checked-helper | `TokenService::mint` | 1 |
| `token.sell` | abi-builder-only |  | 6 |
| `token.update_details` | abi-builder-only |  | 4 |
| `nft.add_del_reserve` | partial-checked-helper | `DeployedNft::addDelReserve` | 4 |
| `nft.approve` | abi-builder-only |  | 2 |
| `nft.burn` | abi-builder-only |  | 4 |
| `nft.create_collection` | partial-checked-helper | `DeployedNft::createCollection`, `DeployedNft::createdCollection` | 2 |
| `nft.delegate` | partial-checked-helper | `NftService::delegate721`, `NftService::delegate1155` | 6 |
| `nft.disable_mint` | abi-builder-only |  | 4 |
| `nft.hold` | abi-builder-only |  | 5 |
| `nft.mint` | partial-checked-helper | `DeployedNft::mint` | 10 |
| `nft.set_approval_for_all` | abi-builder-only |  | 4 |
| `nft.set_token_uri` | partial-checked-helper | `NftService::setTokenUri` | 2 |
| `nft.transfer` | partial-checked-helper | `NftService::transfer721`, `NftService::transfer1155` | 5 |
| `nft.transfer_batch_erc1155` | abi-builder-only |  | 2 |
| `nft.transfer_stake` | abi-builder-only |  | 4 |
| `nft.withdraw` | abi-builder-only |  | 4 |
| `checks.create_del` | partial-checked-helper | `ChecksService::createDel` | 2 |
| `checks.create_token` | partial-checked-helper | `ChecksService::createToken` | 4 |
| `checks.redeem` | partial-checked-helper | `ChecksService::redeem` | 2 |
| `bridge.complete_transfer` | abi-builder-only |  | 1 |
| `bridge.transfer_native` | abi-builder-only |  | 1 |
| `bridge.transfer_token` | abi-builder-only |  | 1 |

This audit added the explicit, codehash-bound RestrictedMultisend allowance/permit workflow and profile-restricted simulated NFT URI setter. Both source URI setter ABIs were already bundled in 0.1.0; an earlier audit draft incorrectly called them absent. Token economy/staking/reset/bridge wrappers generally have ABI builders but still lack equivalent high-level orchestration/DTOs. Checked helpers are marked partial where their profile-specific scope is narrower than the Python request. See audit-operation-parity.json for evidence and exact boundaries.
