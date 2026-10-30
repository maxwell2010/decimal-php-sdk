# Advanced examples

All examples prepare or sign locally. None automatically broadcasts. Code snippets assume `vendor/autoload.php` has been required and application addresses have been supplied.

## Allowance plans

```php
use Decimal\{Contract, Workflows, Units};
$token = Contract::profile($tokenAddress, 'Token');
$amount = Units::parse('2', $tokenDecimals);
$action = $stakingContract->draft($wallet->address, $exactSignature, $abiArguments);
$plan = Workflows::allowance($client, $tokenAddress, $action->to, $amount, $action, resetExisting: true);
// Each approval is an independent EOA transaction. If executed later, wait for
// success and refresh allowance before preparing the next step and final action.
```

A generic arbitrary-call Multicall must not receive ERC20 allowance. The planner rejects the known network Multicall addresses. Custom deployment trust remains the caller's responsibility.

## EIP-712 permit

```php
use Decimal\TypedData;
$permit = TypedData::permit($wallet, $tokenAddress, 202020, $tokenName, $tokenVersion,
    $spender, $amountRaw, $currentNonce, $deadline);
$draft = $token->draft($wallet->address,
    'permit(address,address,uint256,uint256,uint8,bytes32,bytes32)', [
        $wallet->address, $spender, $amountRaw, $deadline,
        $permit['v'], $permit['r'], $permit['s'],
    ]);
```

This helper signs caller-supplied domain/nonce values. `TypedData::checkedPermit` reads the nonce and validates the deployed DOMAIN_SEPARATOR before signing. Obtain and validate the deployed token's actual DOMAIN_SEPARATOR, nonce and version before signing. A permit is authorization and must not be logged as ordinary debug data.

## Safe EOA transaction

```php
use Decimal\SafeTransaction;
$safeTx = SafeTransaction::create($safeAddress, 202020, $innerDraft, $safeNonce);
$signature = $safeTx->sign($wallet);
$packed = $safeTx->packSignatures([$signature], owners: $verifiedOwners, threshold: 1);
$draft = $safeTx->draft($executorAddress, $packed);
```

Owners, threshold and nonce must be read from the Safe at a consistent block. The example uses one EOA signature and threshold 1; do not reuse that threshold for another deployment. Contract signatures and prevalidated signatures are not accepted by this EOA packer.

## ABI events and deployment checks

```php
use Decimal\{Events, Deployment};
Deployment::verify($client, $target, $trustedRuntimeHash, $trustedImplementation);
$events = Events::receipt('ERC721', $successfulReceipt, $target);
```

Indexed dynamic event arguments return their topic hash under `indexedHash`, since the original value cannot be recovered from the topic. Unknown logs are skipped by receipt filtering; malformed known events fail. Versioned testnet NFT creation events require the matching versioned profile.

## Offline Cosmos direct signing

```php
use Decimal\{Protobuf, Cosmos, Address};
$protobuf = new Protobuf();
$message = $protobuf->any('/decimal.coin.v1.MsgSendCoin', [
    'sender' => Address::bech32($wallet->address),
    'recipient' => $recipientBech32,
    'coin' => ['denom' => 'del', 'amount' => '123'],
]);
$cosmos = new Cosmos();
$body = $cosmos->body([$message], 'example');
$signed = $cosmos->signValidated($body, 'decimal_test-1', [[
    'wallet' => $wallet, 'sequence' => $sequence, 'accountNumber' => $accountNumber,
]], [['denom' => 'del', 'amount' => $feeAmount]], $gasLimit);
```

Cosmos chain IDs and raw denomination amounts are separate from EVM chain IDs and DEL display units. Signers must be supplied in the message-required order. The schema codec validates field names and integer ranges. signValidated additionally checks all 43 pinned ValidateBasic policies and binds required signer order; signChecked binds signer order without stateless policy validation. Account roles, ante rules and execution are determined by the node. The signer preserves the Decimal Ethermint 65-byte signature convention evidenced by the Go reference fixture.

## WebSocket subscriptions

```php
use Decimal\WebSocket;
$socket = new WebSocket($officialWebSocketUrl);
$id = $socket->subscribe('newHeads');
try { $notification = $socket->receive(); }
finally { $socket->close(); }
```

This is a blocking transport. It queues interleaved notifications and exposes explicit reconnect/resubscribe with an old-to-new remote ID map. Failed reconnect preserves subscription definitions. It does not silently retry writes and has no transaction submission method.

## Concurrent nonce reservation and external signing

```php
use Decimal\{NonceManager, CallbackSigner};
$nonces = new NonceManager($applicationStateDirectory . '/decimal-nonces.json');
$prepared = $client->prepare($draft, nonces: $nonces);
$external = new CallbackSigner($senderAddress, fn($tx) => $hardwareSigner($tx));
$signed = $external->sign($prepared);
```

The callback must return the raw signed transaction or a SignedTransaction instance. Sender, chain and exact unsigned hash must match the prepared transaction. The nonce store uses a stable lock and atomic snapshots. Do not reset reservations after a timeout or an ambiguous submission; reconcile account nonce/hash state first. CancellationToken is cooperative between bounded synchronous calls.

## Weighted Safe policy and mixed signatures

Read SafePolicy at a pinned block, then use SafeSignatures to validate and pack typed/personal EOA signatures, explicitly selected EIP-1271 interfaces, or prevalidated approvals. The threshold uses the sum of owner weights, not the number of signatures. Guarded delegatecall additionally requires caller-reviewed target runtime and implementation evidence. See docs/EVM_SCOPE.md and tests/evm_release.php for executable examples.


## Named operation services and plans

The runnable examples/parity-offline.php encodes ERC20, bridge and checks requests with an RPC that rejects every call. Named service/read/permit correspondence is listed in FINAL_PARITY_0_1_3.md and the linked domain reports.

```php
use Decimal\{StakingService, TransactionExecutor, TransactionPlan};

// Supply the actual original position/deposit deployment, wrapped DEL and validator center.
$staking = new StakingService($client, $delegation, $wdel, $masterValidator, 'Delegation');
$rebuild = fn() => $staking->plan('decimal.delegate_erc20', $wallet->address, [
    'validator' => $validator,
    'token' => $token,
    'amount' => '1.25',
    'auto_approve' => true,
]);
$plan = TransactionPlan::from($rebuild());
$executor = new TransactionExecutor($client);
$result = $executor->executePlan($plan, $wallet); // No broadcast by default.
```

Planning performs read/simulation calls. A plan needing approvals returns requires_approvals without preparing/signing the dependent action. executePlan accepts an explicit refresh callback and broadcast flag for applications that later enable submission: it confirms each prerequisite, rebuilds state and rejects a changed action before signing. This release validation does not enable submission or run that mode against a node.

TokenEconomy uses human strings for buy/sell/convert and explicit Raw/Wei creation fields. NFT exits require the original deployment/profile; new upgraded mainnet NFT deposits explicitly select Delegation20261030. NftStakingReader uses the distinct NFTStake10 layout, while StakingReader retains fungible Stake7/8. PermitService and NftPermitService prepare data for external signers without needing private keys; provided signatures are bound to the actual typed request.

## Current profile factories

```php
$sdk = new Decimal\Sdk($client);
$tokens = $sdk->tokenEconomy($tokenCenter); // CurrentTokenEconomy: current selectors and guards.
$nft = $sdk->nft($collection, 721, $nftCenter); // Current checked mint/reserve/permit/exit rules.
$legacy = $sdk->legacyNftStaking($originalLegacyDelegation); // Original position route retained.
```

Current staking/conversion/NFT request methods default to no automatic approval. Choose it explicitly to obtain approval drafts. CurrentTokenEconomy::updateDetails returns two ordered actions; TransactionExecutor::executeSequence defaults to an unsigned non-atomic plan. Submission requires an explicit broadcast flag and refresh callback between confirmed steps. The result retains per-step receipts and sums budget/paid fees, without claiming rollback of earlier successful actions.

Client::refreshResult updates an existing result without signing/resending, preserves the original fee budget and obtains actual fees only from a receipt or the source-supported legacy transaction price fallback. Unknown dynamic paid prices remain null. See CURRENT_PARITY_0_1_4.md and domain manifests for exact current request correspondence.
