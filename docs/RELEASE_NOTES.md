# Decimal PHP SDK v0.1.4

Source/API parity release covering the original matrix and the current recovery-014 Python request profiles, independently implemented and cross-reviewed against Go/Python/C#. No blockchain transactions or deployments were submitted. Explicit legacy/raw APIs and original position addresses remain available.

## Added

- Current token/NFT factories, testnet fee/symbol/reserve rules, checked NFT mint/delegate permits and whole-position exits, structured validator metadata and additional legacy/reset/completion requests.
- Fee budget/actual fee aliases, transaction refresh without resending, checks signer/hash helpers and confirmed non-atomic sequence execution with state refresh.

- Close all 55 entries of the operation matrix: 54 named request workflows and one intentional rejection of unsafe generic Multicall ERC20 allowance, with verified RestrictedMultisend retained.
- ERC20 and token economy services, reserve state/quotes, exact-input/output trades, creation fees, approval/reset/permit plans and creation reports.
- Staking/reset/transfer/withdraw services, validator reads/management, pinned snapshots, frozen records and withdrawal receipts.
- NFT collection/mint/reserve/batch/stake/exit APIs, ordered movement/URI reports and distinct unified NFTStake10 reads.
- ERC20/DRC721/DRC1155 external permit preparation and signature/domain/nonce/owner validation.
- Bridge/checks services, common TransactionPlan/TransactionExecutor, fee quotes, registry snapshots and portable transaction/receipt reports.

## Validation

7872 offline checks, independent calldata/digest fixtures, injected approval/cancellation/error sequences and installed-package tests. Publication is gated on Linux, Windows and macOS with PHP 8.2-8.5. Composer audit is a required independent check. Stake7/8, old/new events, original legacy exits and explicit unified NFT deposits remain supported.

[Parity acceptance and evidence](https://github.com/maxwell2010/decimal-php-sdk/blob/main/docs/CURRENT_PARITY_0_1_4.md) links the complete matrix and domain reports. The acceptance scope is source/API behavior. Mutable roles/balances, funded lifecycle execution and cross-chain settlement require separate application acceptance.

## Install the latest stable release

```sh
composer create-project --repository=https://github.com/maxwell2010/decimal-php-sdk/releases/latest/download/packages.json mintcandy/decimal-web3-sdk decimal-php-sdk
```

Composer 2, PHP 8.2+ and curl/bcmath/gmp/mbstring are required; ZIP extraction needs PHP zip or unzip/7z. The command creates a new directory. Existing lockfiles remain pinned until explicitly updated.

## Offline example

```php
require 'vendor/autoload.php';

$client = new Decimal\Client(Decimal\Network::testnet());
$bridge = new Decimal\BridgeService($client, '0x3333333333333333333333333333333333333333');
$draft = $bridge->transferNative(
    '0x1111111111111111111111111111111111111111',
    '0x2222222222222222222222222222222222222222',
    '1000000000000000000', '1000', '75', '42'
);
echo json_encode($draft->rpc()); // Encoding only; no RPC or broadcast.
```

Addresses above are placeholders. See examples/parity-offline.php and docs/EXAMPLES.md for service planning and common execution.

[Permanent ZIP](https://github.com/maxwell2010/decimal-php-sdk/releases/latest/download/decimal-php-sdk.zip) and [SHA256SUMS](https://github.com/maxwell2010/decimal-php-sdk/releases/latest/download/SHA256SUMS) follow the latest stable release. Versioned ZIP, alias ZIP, packages.json and SHA256SUMS are attached. Existing release assets remain immutable.
