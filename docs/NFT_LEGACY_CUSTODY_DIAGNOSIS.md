# Legacy NFT exit custody diagnosis

The additional opt-in audit pins mainnet block `0x20c281d`, matching the existing position/asset evidence. Twelve permitted RPC requests resolve the asset beacon, read its implementation runtime, read the legacy pause flag and independently simulate a custody transfer and completion. No signatures, sends, estimates or state overrides are used.

The NFT proxy embeds beacon `0xd2f60b34f7a70229c1556d4e0705f9b8f7b107b3`. At this block, `implementation()` returns `0xe0f7399e648a140d8242c98930ee3d0570d3738b`. This is a historical observation, not a trusted deployment manifest or a claim about the current implementation.

The earlier 0.1.6 narrative did not interpret the custom revert bytes already present in its raw captures. The new independent calls reproduce these payloads and add a pause-state read:

- Legacy `paused()` returns true. `complete([0])` from the recorded delegator returns `0xd93c0665`, matching `EnforcedPause()`. This supports pause as the immediate admission blocker for this completion at this block.
- NFT `ownerOf(1)` and direct `safeTransferFrom(legacy, delegator, 1)` from the legacy contract address return `0x7e273289` with argument 1, matching `ERC721NonexistentToken(uint256)` in the SDK ERC721 ABI. The asset cannot supply this token through the independently simulated transfer at this state.

Selectors were recomputed with the SDK Keccak implementation. Error semantics are supported by primary [OpenZeppelin Pausable source](https://github.com/OpenZeppelin/openzeppelin-contracts/blob/v5.0.0/contracts/utils/Pausable.sol) and [ERC721 source](https://github.com/OpenZeppelin/openzeppelin-contracts/blob/v5.0.0/contracts/token/ERC721/ERC721.sol). Selector matches and pause state do not authenticate the deployed implementation source. They do not establish the token's historical fate, prove the nested transfer path or guarantee completion after a future unpause. No unpause or repair is attempted. This is contract-state evidence, not an SDK encoding defect.

[Recorded requests and raw errors](live/nft-legacy-exit-custody.json) retain exact caller, target, calldata and block. `tests/nft-legacy-custody-regressions.php` replays the three custom errors using those exact request parameters and rejects any unexpected request. A successful decode of the frozen record alone must never be treated as proof that completion will succeed.

Reproduce with `tools/live_rpc_nft_legacy_exit_custody.php`, configuring a trusted CA bundle if required by the environment. The tool remains opt-in and does not run through Composer or CI. The final artifact records twelve requests; the initial exploratory ten-request run is not retained as a separate acceptance artifact.
