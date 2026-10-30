# PHP SDK 0.1.4 current-profile acceptance

The 0.1.3 release completed the original 55-entry snapshot catalog. A final comparison with the explicitly supplied recovery-014 Python worktree identified newer request branches and guards. This release includes those differences while preserving explicit original/raw APIs. Go/C# correspondence and the original signing/ABI/Cosmos corpora remain included.

## Coverage

- The [operation matrix](AUDIT_OPERATION_PARITY.md) retains all 55 original entries and lists additional current request/capability mappings separately. There are no remaining ABI-only/partial rows. The unsafe generic Multicall ERC20 allowance path is intentionally disabled; verified sender-restricted batching remains available.
- [Current token semantics](PARITY_TOKENS_CURRENT.md): exact trade selectors, opt-in automatic approval, testnet symbol/commission/reserve rules, ordered identity/max-supply updates, mintable checks, GasCenter permit conversion, explicit legacy minimum-supply setter and pinned raw/formatted properties.
- [Current staking/validator semantics](PARITY_STAKING_CURRENT.md): completion and explicit legacy penalty selectors, structured metadata/current validator requests, opt-in automatic approval and external permit delegate/hold inputs.
- [Current NFT semantics](PARITY_NFT.md): tuple6 burnable collection creation, active/mint/reserve eligibility and minimum/rate checks, both mintByPermit variants, unified ERC721 delegateNFTByPermit, checked external permits, URI legacy opt-in, whole-position unified exits and seven legacy hold/reset/complete request variants.
- Common API additions: positive native burn, sender-scoped checks signing/hash extraction, fee budget versus actual fee aliases, transaction result refresh and confirmed multi-action execution with state refresh between steps. Refresh never signs or resends; legacy gasPrice fallback is not used as a dynamic transaction's paid price.

`Sdk` factories choose CurrentTokenEconomy and NftCurrentOperations. Original TokenEconomy and raw NftOperations remain available through explicit legacy/raw factories. Supplied deployment addresses are never replaced globally. New mainnet NFT deposits require an active unified route; legacy reads/exits retain their original address. Fungible Stake7/8, frozen nested stakes and distinct NFTStake10 keep their own layouts and field meanings.

## Verification

The complete suite passes 7872 offline checks. Independent calldata/digest fixtures cover current mint/delegate permits, GasCenter and all seven legacy NFT operations. Injected state tests cover reserve conflicts/minima/rate math, stale domain/nonce/owner, whole-position exits, metadata, defaults, pending receipt refresh, dynamic price ambiguity and multi-step confirmation/refresh ordering. Source manifests record exact recovery-014 file hashes, including nft.py/nft_operations.py, staking_operations.py, validator_operations.py, token.py, transactions.py and erc20.py.

Implementation agents cross-reviewed token, staking, NFT and common APIs. Final review corrected Unicode boundary validation and confirmed reserve arithmetic, selectors/argument ordering, legacy routing and duplicate ID/amount preservation. Current request branches are covered by named helpers/readers; language-specific class names and runtime scheduling are not copied as acceptance requirements.

No blockchain transactions, actual approvals, deployment or signatures from operating accounts were performed. Test keys are public deterministic fixtures. No funded lifecycle or historical replay campaign was repeated. Source/API parity does not establish mutable node authority, balances, admission or cross-chain settlement. Those remain explicitly outside this offline release's evidence.

## Install

```sh
composer create-project --repository=https://github.com/maxwell2010/decimal-php-sdk/releases/latest/download/packages.json mintcandy/decimal-web3-sdk decimal-php-sdk
```

The permanent metadata and ZIP links select the latest stable release. Existing versioned assets, including 0.1.3, remain immutable. Publication is gated on Linux/Windows/macOS and PHP 8.2-8.5, Composer audit and installed-package verification.
