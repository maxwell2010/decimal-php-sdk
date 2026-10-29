# Decimal PHP SDK v0.1.0

First stable source release of the independent Decimal PHP SDK by MintCandy.

## Install the latest stable SDK with one command

```sh
composer create-project --repository=https://github.com/maxwell2010/decimal-php-sdk/releases/latest/download/packages.json mintcandy/decimal-web3-sdk decimal-php-sdk
```

This permanent metadata URL selects the current stable release and installs dependencies. Requires Composer 2, PHP 8.2+ and curl/bcmath/gmp/mbstring; Composer needs PHP zip or unzip/7z to extract the source archive. Use a new destination directory. Existing lockfiles remain pinned until explicitly updated.

Permanent source download: https://github.com/maxwell2010/decimal-php-sdk/releases/latest/download/decimal-php-sdk.zip

Versioned ZIP, permanent-name ZIP, packages.json and SHA256SUMS are attached. The Composer metadata points to the immutable versioned archive and verifies its hash. Future stable tag releases pass the test matrix before updating GitHub Latest.

## Included

- 27 ABI profiles, 577 typed bindings and source/deployment evidence.
- BIP39/BIP32 wallets, EIP-155/2930/1559, contract creation, checked permit, exact external signer binding, nonce coordination and fee caps.
- Weighted Safe with typed/personal EOA, EIP-1271 and prevalidated signatures; checked delegatecall evidence.
- Guarded NFT/staking/token/checks helpers, event decoding and verified pinned-block reads.
- Bounded read-only RPC failover, API/indexer helpers, WebSocket notification queue and explicit reconnect.
- 393 Cosmos schemas, all 43 pinned ValidateBasic branches, source signer ordering, validated direct signing, strict envelopes, fixed-point fees and native HTTP/2 queries/simulation.
- English/Russian guides, independent fixtures, node policy corpora, loopback integration, concurrency and single-command installation tests.

Composer audit reports no known advisories for the lockfile. No blockchain transactions were submitted. Stateless validation does not establish roles or ante/execution admission; live funded-wallet acceptance and an external cryptographic audit have not been performed. The exact supported scope is in docs/PARITY.md, docs/EVM_SCOPE.md and docs/COSMOS_VALIDATION.md.
