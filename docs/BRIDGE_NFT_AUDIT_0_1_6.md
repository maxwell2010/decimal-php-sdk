# Bridge discovery and extended NFT eth_call audit

Three agents investigated public Bridge sources/deployments, NFT collection actions and NFT position/exits. All live RPC calls used the strict read-only harness and verified TLS. No transaction, gas estimate, account signature, paid lifecycle or deployment was performed. Existing NFT ownership was read from chain; no private keys or public fixture credentials were used.

## Bridge result

An operational current mainnet Bridge and a complete source-matched current testnet Bridge ABI were **not established**. This is an explicit remaining external-data limit, not a claim that no Bridge exists.

[Discovery evidence](BRIDGE_DISCOVERY.md) compares Go/Python/C#/official JS sources, the current node master a46cb48d7965c1a64a495cebb22e735dfe4b9da3 and historical decimal-contracts source. The node tree provides no bridge artifact. Historical swap/redeem contracts use a different interface and were not substituted for the SDK's bridge-v2 profile. Mainnet bridge GraphQL datasets report missing deployments. The testnet GraphQL sources timed out; the prior official dataset supplied the independently rechecked candidate address below.

The testnet candidate proxy is 0x827ea5381adb912ed0befda26afe8cdc5a5e050a, with EIP-1967 implementation 0x06d7ac3874adc1f773c5165e91077c8037b789ff. Actual calls observe a full chainId word of 202020 and minimalServiceFees(uint256) returning zero for route 2. The uint16 getter and wormhole() revert. The Token-shaped compact API ABI is unrelated: even name() reverts. A zero exploratory fee is not proof of route support, ABI semantics or settlement.

The implementation runtime was independently read at the recorded block: keccak256 0xf4304c13b5611dafda19a48666c12e43a9a4333c83d978eacbd32785fd5e4383 and metadata SHA256 ca45531f338c2f064eb197a35139b88c46573f9b8a4c201804748688e4637481 match the prior fingerprint. [Raw implementation read](live/bridge-implementation.json) retains the evidence.

Public metadata retrieval also did not yield a complete authenticated ABI: Sourcify full/partial matching entries return 404; metadata IPFS gateways returned rate/permission/TLS/timeouts. [Retrieval attempts](bridge-metadata-retrieval.json) retain the content identifier and outcomes. Certificate verification was never disabled. No native/token selector was guessed from the uint256 getter, and no source profile was silently rewritten.

## SDK safeguard

BridgeService::verified and Sdk::verifiedBridge bind an explicit destination to caller-reviewed runtime/implementation evidence through VerifiedContract, probe the selected uint16 fee interface and recheck runtime before building actions. Verified quotes report the concrete verified block. Another destination or a service fee below the reported minimum rejects.

**Bridge auto-approval planning now requires this verified binding.** The former raw transferToken(..., autoApprove=true) rejects before any allowance read. Raw unsigned constructors remain available with autoApprove=false for deliberate ABI encoding. The new binding was also exercised against the actual candidate using the observed fingerprints for a negative test: it rejected the uint16 fee interface before any approval plan. These 18 additional guarded requests are in [bridge-binding.json](live/bridge-binding.json). Observed fingerprints in this negative test are not advertised as trusted production provenance.

The incompatible recorded candidate's uint16 rejection is preserved in regression tests; it cannot become an approval plan through a silent uint256 fallback.

The guard verifies supplied deployment evidence and the observed fee interface. It is not automatic discovery, proof of all Bridge methods, proof of peer configuration, attestation authenticity or settlement. Trusted runtime hashes must come from a caller's reviewed provenance, not merely be copied from an untrusted endpoint's current code. No working Bridge address is advertised by this release.

## Extended NFT results

[Collection actions](NFT_EXTENDED_READ_AUDIT.md) record 76 permitted RPC calls and 20 passed checks. Mainnet reserveless mint and operator approval clearing succeed in eth_call. Testnet ERC721 and ERC1155 creator-context native-reserve mint, actual-owner self-transfer, approval clearing, burn and ERC1155 batch self-transfer succeed. Each call uses the original pinned state independently; no minted/burned/transferred token or approval persists. No valid permit was fabricated or signed.

[Position evidence](live/nft-positions.json) records 50 calls: legacy Stake7, unified NFTStake10 and freeze reads, plus bounded last-500-block NFT event queries. No current funded unified NFT position was found in that window. The unified index 0 record is fungible type 4 on both networks and is correctly rejected by the NFT reader. Invalid completion indices produce the expected node panic in eth_call.

A real mature mainnet legacy frozen index 0 remains active, but complete([0]) from its recorded delegator reverts. [Asset-state diagnosis](live/nft-legacy-exit-asset.json), 24 additional reads at the same block, observes ownerOf(1) reverting and zero ERC721 balances for the legacy contract/delegator while reserve/frozen metadata remain. The exact completion failure is **unresolved**; burn, migration or an SDK defect is not inferred from those observations. This exit must not be labeled successful or silently removed from reports.

No new NFT decoding/encoding defect was confirmed in the selected cases. Source/API tests do not guarantee live contract-state admission.

## Tests and reproduction

The combined offline suite passes **8095 checks**. Added regressions replay actual collection action returns, legacy/unified position shapes and completion errors, and check bridge runtime/route/fee guards. A synthetic caller-trusted deployment is used for positive binding tests; the negative fee-profile response is an actual recorded RPC revert. This does not mislabel a synthetic fixture as a live compatible Bridge.

Run live tools only when explicitly wanted, with the environment's trusted CA bundle:

```sh
php -d curl.cainfo=/path/to/trusted/cacert.pem tools/live_rpc_bridge_discovery.php
php -d curl.cainfo=/path/to/trusted/cacert.pem tools/live_rpc_nft_extended.php
php -d curl.cainfo=/path/to/trusted/cacert.pem tools/live_rpc_nft_positions.php
php -d curl.cainfo=/path/to/trusted/cacert.pem tools/live_rpc_nft_legacy_exit_asset.php
```

They are not invoked by Composer/CI. [The recorded final RPC artifacts](live/bridge-nft-summary.json) total 195 attempts for this audit; public source/metadata HTTP requests are recorded separately. Initial exploratory attempts are not counted as successful acceptance. Standard CI and installation tests replay captures offline. Release 0.1.6 preserves permanent latest links and all prior immutable release archives.
