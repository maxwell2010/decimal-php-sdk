# Read-only RPC audit of 0.1.4, diagnostics patch in 0.1.5

Three agents audited staking/validators, tokens and NFTs against the actual mainnet and testnet RPCs. The parent checked registry/proxy reads, receipts, Checks and nonpersistent ERC20 calls. The shared harness uses the SDK's actual HTTP Rpc implementation with a strict outer method allowlist, a request budget and bounded historical logs. It blocks signing, estimates and send methods before HTTP.

## Recorded evidence

The saved final runs contain **429 permitted RPC attempts** and **159 passed checks**. This includes 428 attempts with the configured trusted CA and one expected unconfigured-CA diagnostic failure. Initial exploratory TLS failures are not counted as successful reads. [The summary](live/summary.json) records exact method counts and links the raw request/result/error artifacts. Every recorded method is a read or eth_call; there are no transaction sends, estimates or signing requests.

| Area | Recorded requests | Result |
| --- | --- | --- |
| Staking/validators | 63 | Mainnet four active eight-field stakes, regular stake and both networks' validator reads; historical 8 StakeUpdated + 8 StakeAmountUpdated decoded |
| Tokens | 171 | Registry/WDEL, reserve and reserveless state, properties and all four quote directions decoded |
| NFT | 100 | Mainnet legacy reserveless collection and testnet ERC721/1155 state, owner/balance, approval reads, reserve/rate, URI and domain reads |
| Registry/receipts/core | 48 | Pinned registry snapshots, standard implementation slot reads, native balance/gas price, actual historical receipt reports and expected unknown-selector reverts |
| Checks | 22 | Explicit public configured addresses on both networks: runtime, owner, implementation and sender-scoped nonce reads |
| Nonpersistent ERC20 calls | 24 | approve(0) and transfer(0) eth_call returned true on both networks; over-balance transfer eth_call returned the expected node revert |
| TLS diagnosis | 1 | Missing local CA trust produced errno 60, now preserved in the SDK exception |

Each area pins a concrete block for its contract reads; these are separate snapshots, not a single global atomic snapshot. Staking/token mainnet evidence includes block 0x20c2675; the corresponding testnet heads include 0x157369a and 0x157369c. Later core/Checks/call probes record their own blocks in the artifacts. Raw data and UTC timestamps are retained; quantities are never converted through floating point.

## Findings and limits

No decoder, state calculation or quote defect was confirmed in the checked token, staking, validator or NFT paths. The mainnet collection supports legacy getNftType: the existing explicit NftReader::nftType(true, block) works. Its optional getNFTType/getRefundable views revert; those errors remain visible rather than becoming invented values.

Both registries return zero for checks and bridge slugs. The SDK correctly rejects unregistered targets. Checks were therefore queried separately at the public addresses recorded in the reference Python configuration. Mainnet Checks owner and implementation returned zero; these are observed read values, not a claim about readiness or proxy configuration. No registered bridge deployment was available for business/settlement validation, and no alternative address was guessed.

Unified NFTStake10 empty-position reads and freeze time pass on both networks. This does not establish a funded NFT lifecycle. Testnet zero fungible positions are actual successful decodes, distinct from RPC failures. Historical logs were read within a bounded 385-block window. No history replay or paid lifecycle was performed.

All approve/transfer action probes used eth_call with zero native value and no signature. They do not persist approvals, balances, logs or any other state. No actual blockchain transaction or deployment was sent.

## Confirmed SDK fix

0.1.4 discarded cURL errno/error details and exposed only RPC transport failed with HTTP status 0 for a local TLS failure. 0.1.5 captures diagnostics before closing the handle and includes http_status, curl_errno, curl_error and response_limit_exceeded in RpcTransportException::rpcData. The message distinguishes HTTP, cURL/TLS and response-cap failures. The SDK does not explicitly append the URL, request or response body; libcurl supplies its own error text, which can mention a host or CA file path.

Read-only failover and ambiguous-write retry policy are unchanged. Certificate verification is unchanged. The missing local trust bundle was an environment issue, resolved for the audit by supplying a trusted CA bundle through curl.cainfo; TLS was never disabled.

## Regression and reproduction

The offline suite passes **7947 checks**, including real captured stake/event/token/NFT responses and eth_call successes/reverts. A loopback test additionally exercises actual SDK HTTP 503, connection failure, response size cap and read failover diagnostics. CI runs these locally, without contacting a blockchain.

Live tools are explicitly opt-in and are not invoked by Composer tests or CI:

```sh
php -d curl.cainfo=/path/to/trusted/cacert.pem tools/live_rpc_staking.php
php -d curl.cainfo=/path/to/trusted/cacert.pem tools/live_rpc_tokens.php
php -d curl.cainfo=/path/to/trusted/cacert.pem tools/live_rpc_nft.php
php -d curl.cainfo=/path/to/trusted/cacert.pem tools/live_rpc_core.php
php -d curl.cainfo=/path/to/trusted/cacert.pem tools/live_rpc_checks.php
php -d curl.cainfo=/path/to/trusted/cacert.pem tools/live_rpc_eth_call.php
```

Use the trusted CA bundle provided by your PHP/OS environment; do not disable verification. Windows installations with GMP disabled may also need -d extension=gmp. Scripts write fresh capture files into docs/live, so review those snapshots before committing them. Unclassified check failures return a nonzero exit code; unsupported deployment views, unregistered slugs and coverage limits remain explicit.

Release 0.1.5 contains the diagnostics patch, captured evidence and offline regressions. The permanent latest installation links remain unchanged; published versioned archives stay immutable.
