# Pinned simulation and revert diagnostics

```php
require 'vendor/autoload.php';

$client = new Decimal\Client(Decimal\Network::testnet());
// $draft is an unsigned TransactionDraft built with your intended sender.
$errors = [['type'=>'error', 'name'=>'EnforcedPause', 'inputs'=>[]]];
try {
    $bytes = $client->simulate($draft, '0x1573910');
} catch (Decimal\RpcException $error) {
    echo json_encode(['code'=>$error->getCode(), 'message'=>$error->getMessage(),
        'data'=>$error->rpcData, 'decoded'=>$error->revertReason($errors)]);
}
```

Supply a block from your selected network and a draft appropriate to its state. Without the optional block argument, simulate retains its pending default. Only eth_chainId and eth_call are requested. No wallet, signature, estimate, send, state override or approval is involved.

The decoder returns kind error, panic or custom for a canonical recognized payload; empty, unknown, malformed or ambiguous otherwise. It preserves original bytes. Panic codes use decimal strings. Custom errors require explicit ABI entries, including tuple components where needed; duplicate signatures do not create ambiguity. Distinct custom error signatures sharing a selector and valid payload produce ambiguous candidates. Built-in Error/Panic selectors retain standard interpretation. Malformed ABI structure raises InvalidArgumentException.

RpcException::revertReason returns null for absent/non-hex error data or transport exceptions. Nested provider objects are left intact. RevertReason::decode can decode explicit hex bytes directly without RPC. Decoding describes bytes, not their provenance: errors may be forwarded by nested calls or fabricated. An empty revert does not identify its cause. A successful eth_call neither persists state nor guarantees future admission.

See [Solidity ABI errors](https://docs.soliditylang.org/en/latest/abi-spec.html#errors), [panic definitions](https://docs.soliditylang.org/en/latest/control-structures.html#panic-via-assert-and-error-via-require) and the [0.1.7 audit](NFT_FOLLOWUP_0_1_7.md).
