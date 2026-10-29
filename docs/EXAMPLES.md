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
$signed = $cosmos->sign($body, 'decimal_test-1', [[
    'wallet' => $wallet, 'sequence' => $sequence, 'accountNumber' => $accountNumber,
]], [['denom' => 'del', 'amount' => $feeAmount]], $gasLimit);
```

Cosmos chain IDs and raw denomination amounts are separate from EVM chain IDs and DEL display units. Signers must be supplied in the message-required order. This codec validates field names and integer ranges, but does not reproduce upstream ValidateBasic, account roles, ante rules or signer extraction. Do not broadcast unvalidated schema-only messages. The signer preserves the Decimal Ethermint 65-byte signature convention evidenced by the Go reference fixture.

## WebSocket subscriptions

```php
use Decimal\WebSocket;
$socket = new WebSocket($officialWebSocketUrl);
$id = $socket->subscribe('newHeads');
try { $notification = $socket->receive(); }
finally { $socket->close(); }
```

This is a blocking, experimental transport. It does not multiplex outstanding requests or reconnect/resubscribe automatically. It exposes only subscription operations; it has no transaction submission method.
