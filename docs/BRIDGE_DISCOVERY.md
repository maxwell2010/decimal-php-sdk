# Current bridge discovery

Read-only discovery compared Go, Python, C# and official JS SDK sources with the node repository and actual mainnet/testnet RPC. No signature, transaction, approval, gas estimate or deployment was performed.

## Source evidence

The current public go-smart-node master equals a46cb48d7965c1a64a495cebb22e735dfe4b9da3. Its recursive GitHub tree has no bridge path. This establishes no current bridge ABI/address from that repository, rather than proving no bridge deployment exists.

Python and C# expose caller-supplied uint16 bridge-v2 drafts. Go similarly requires an explicit deployment ABI. These interfaces do not supply authoritative current deployment addresses. Official JS discovers bridge objects through TheGraph, separately from ContractCenter. It does not establish an ABI from a registry slug.

Fresh official JS GraphQL queries returned deployment-not-found errors for mainnet bridge, bridgeETH and bridgeBSC. The three testnet GraphQL endpoints timed out during TLS handshake. The prior recorded official testnet bridge dataset names proxy 0x827ea5381adb912ed0befda26afe8cdc5a5e050a and implementation 0x06d7ac3874adc1f773c5165e91077c8037b789ff. The compact API's token-shaped ABI was not trusted.

Historical official decimal-contracts source at 018a6c6d00616c3e24ae44a8fac429b2ccabbd12 contains bridgeBsc.sol and a bridgeEth directory. This historical source is not deployment provenance for the current uint16 interface and was not substituted for it. Source hashes, declarations and exact responses are in bridge-current-discovery.json.

## Actual RPC characterization

The latest saved bounded run contains 25 permitted RPC calls. Both networks reject zero results for exact bridge and bridge-v2 registry slugs. The testnet candidate proxy runtime hash remains 0xb199d4e593e9c2c68b5500d4a59173e0f583d6223ae9b968998fd5e1faac4b78 and EIP1967 implementation remains the address above.

Actual name()/ERC20 metadata calls revert, disproving the unrelated Token ABI returned by the compact API. The runtime accepts minimalServiceFees(uint256) for route2 (zero) and chainId() (202020), but rejects minimalServiceFees(uint16) and wormhole(). chainId was decoded as a full ABI word for observation; its Solidity output width is not inferred from this alone. A zero fee for one route does not certify that route is enabled or settled.

The fresh observations establish an incompatible candidate interface, not a complete reviewed current Bridge protocol. No current mainnet Bridge address was verified. No uint16 builder was silently adapted to uint256 or pointed at a guessed address. Further source-backed ABI/deployment evidence is required before claiming native/token/settlement acceptance.

Use tools/live_rpc_bridge_discovery.php with the existing verified-CA read-only harness to reproduce RPC characterization. Raw results are in docs/live/bridge-discovery.json. Earlier exploratory attempts and one rejected uint16 output-decoding probe were not used as successful evidence.
