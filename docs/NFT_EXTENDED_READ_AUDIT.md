# Extended NFT eth_call acceptance

The saved run in [live/nft-extended.json](live/nft-extended.json) contains 76 guarded RPC reads/calls, 20 passed checks and three pinned collection snapshots. Only eth_chainId, eth_blockNumber and eth_call were used. No signatures, estimates, sends, funded replay or deployment occurred.

Mainnet block 0x20c2814: the known reserveless ERC721 collection's actual creator successfully simulated operator-approval clearing and mint. The mint returned token ID 1 inside the ephemeral call; no token was created on chain. No existing owned token was invented for transfer/burn tests in this previously empty collection.

Testnet block 0x1573843: existing ERC721 ID 2 and ERC1155 ID 987654 ownership/balance were read, then owner-context operator approval clearing, ERC721 token approval clearing, self-transfer, burn and ERC1155 batch self-transfer all returned successful void results. Creator-context native reserve mint also succeeded for both standards using the actual MIN_RESERVE, with a new ERC1155 ID in the simulated call only.

Each operation is a separate eth_call against the original pinned state. The calls do not compose a persisted lifecycle; clearing approval or burning in one call does not affect the next. Runtime authorization was tested using public on-chain from addresses without access to their keys. These results do not assert that the SDK owns those addresses or can submit transactions from them.

No SDK defect was confirmed in these collection/mint/burn/transfer/approval paths. Valid permit workflows were not fabricated: no new signature was generated. Unified NFT staking and Bridge validation are separate audit domains. The offline fixture test reuses the captured action drafts/results and verifies the recorded method allowlist, response shape and ephemeral mainnet mint ID without contacting a node.
