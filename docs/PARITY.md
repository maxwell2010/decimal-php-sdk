# Coverage and remaining parity work

This is a source-level comparison with the local Go, Python and C# references available on 2026-10-29. Counts describe exported ABI bindings or schemas, not distinct transaction workflows, acceptance tests or a percentage of parity.

| Area | PHP implementation and evidence | Remaining work |
| --- | --- | --- |
| Contract ABI | 27 profiles, 577 generated functions; independent Python calldata fixtures and input decode checks | Deployment compatibility must be verified separately; imported Python profiles may include unreleased changes |
| Native DEL and ERC20 | Native drafts, memo, ABI transfers/approve, allowance planning with zero-reset, fee estimation, exact integer units | Concurrent nonce coordinator, all Python workflow/report DTOs |
| NFT, staking, token centers, checks, bridge, validator operations | ABI builders/readers plus Go reference operation restrictions; event decoding from supplied ABI | High-level orchestration, economic/role checks and complete receipt DTO parity are not finished |
| Wallet | Private keys, English BIP39 validation/generation, NFKD passphrases, BIP32 paths and address sequences, EIP-55 and 20-byte Bech32 | Other mnemonic wordlists; generic Cosmos 1..255-byte addresses |
| EVM signing | EIP-155, EIP-2930 and EIP-1559, access lists, low-s signatures, strict RLP, hash and sender recovery | Contract creation transactions, hardware/external signer interface |
| EIP-712 | Nested structs/arrays, strings/bytes, typed hashes, signature/recovery, EIP-2612 permit helper | Supports checkedPermit nonce/domain verification; deployment-specific nonstandard permit variants still require explicit ABI |
| Safe | SafeTx typed hash, EOA signatures, owner/threshold checks supplied by application, sorted packing, exec draft, multisend byte packing | Automatic owner/threshold/nonce snapshot, EIP-1271 and prevalidated signatures, guarded delegatecall workflows |
| Deployment | Runtime hash verification, EIP-1967 implementation and beacon lookup, Contract Center address resolution | Automatic versioned deployment-profile binding and all Go policy/snapshot checks |
| RPC | Strict IDs/errors, read/call, simulation, fee and balance checks, explicit broadcast, receipt wait | Multi-endpoint failover, async cancellation, retry policy, Python telemetry and agents |
| REST/indexer | Relative-path bounded GET, pagination and wallet stake helpers; Cosmos account/balance/transaction reads | Full Python normalization DTOs and convenience routes |
| WebSocket | Blocking TLS transport, masked client frames, ping/pong, fragmentation, subscribe/unsubscribe | Live-provider tests, subscription queue/reconnect/resubscribe, concurrent request multiplexing; transport is experimental |
| Cosmos schemas | 393 message descriptors and 43 transaction type URLs from the pinned Go descriptor set; strict schema field encoding | Decode API, native gRPC transport, upstream ValidateBasic differential corpus and fee convergence are not implemented |
| Cosmos signing | Offline direct-mode body/auth/sign-doc/TxRaw and Ethermint signatures; byte-for-byte Go reference fixture | Derive and enforce message signer order, complete business validation, simulation/submission/confirmation orchestration |
| Packaging | Lockfile, English/Russian README, examples, source ZIP, checksums, CI | Packagist publication and funded-wallet acceptance campaign are separate tasks |

No blockchain transaction has been submitted for this release. The public alpha must not be described as full parity. Missing work above is explicit rather than replaced with placeholder methods or successful no-op implementations.

## Dependency advisory

The previous web3.php/ethereum-util chain required phpseclib 2.x and triggered PKSA-12sz-bcny-pk4m. Those packages and phpseclib were removed. ABI conversion now uses an SDK-owned strict codec and wallet addresses use the existing secp256k1 dependency directly. The RLP dependency was also removed after an independent access-list fixture exposed loss of leading-zero storage-key bytes. The replacement preserves byte strings and rejects noncanonical encodings. All previous calldata/signature fixtures remain passing. No audit exceptions or fake package replacements are configured.
