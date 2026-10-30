# Bridge public-source follow-up

The public-source follow-up did not establish a current mainnet Bridge deployment or recover authenticated metadata for the known testnet candidate. The existing uint16 profile remains unchanged. No candidate is promoted to an operational deployment.

## Additional sources

The complete, non-truncated master trees of official repositories `decimalteam/dsc-evm-docs`, `decimalteam/decimal-testnet-test-sub`, `decimalteam/dsc-go-sdk` and `decimalteam/decimal-php-sdk` contain no Bridge-named paths. This is a bounded path discovery result, not proof that no bridge-related content exists anywhere. The test subgraph's `abis/Contract.json` was inspected and exposes ordinary ERC20/Ownable functions, not the candidate's Bridge interface.

The official JS SDK's `src/endpoints.ts` identifies `https://testnet-nft-ipfs.decimalchain.com/` as its IPFS host. A bounded GET of the implementation metadata CID through that host timed out during TLS handshake. Additional public gateways returned rate limits or expired-certificate errors. Certificate validation remained enabled. The expected SHA256 was fixed before retrieval, so an unrelated gateway response cannot become an accepted ABI.

[Raw retrieval and repository evidence](bridge-public-research-followup.json) records exact URLs, repository tree hashes and outcomes. The probes are public HTTP GETs only. No blockchain transaction, signature, gas estimate, state override or deployment was performed.

## Remaining evidence needed

Native/token transfer and settlement compatibility still require complete source-matched ABI and reviewed deployment provenance. A runtime selector, zero fee response or token-shaped explorer ABI is insufficient. The previous [Bridge/NFT audit](BRIDGE_NFT_AUDIT_0_1_6.md) and its explicit limits continue to apply. No SDK source defect was established by this source-retrieval follow-up.
