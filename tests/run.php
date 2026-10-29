<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Decimal\{Units, Network, Rpc, RpcException, Client, Contract, TransactionDraft, PreparedTransaction, Wallet};

$count = 0;
function same(mixed $a, mixed $b): void { global $count; ++$count; if ($a !== $b) throw new RuntimeException('Mismatch: ' . var_export([$a,$b], true)); }
function rejects(Closure $f): void { global $count; ++$count; try {$f();} catch (Throwable $e) {return;} throw new RuntimeException('Expected rejection'); }
$address = '0x' . str_repeat('12', 20);
same(Units::parse('1.000000000000000001'), '1000000000000000001');
same(Units::format('1000000000000000001'), '1.000000000000000001');
same(Units::parse('0', 0), '0');
same(Units::format('100', 0), '100');
foreach (['1e18','-1','01','0.0000000000000000001'] as $bad) rejects(fn()=>Units::parse($bad));
$max = bcsub(bcpow('2','256'), '1'); same(Units::decimal(Units::hex($max)), $max);
rejects(fn()=>Units::uint(bcpow('2','256')));
rejects(fn()=>new Network(0,'http://localhost', $address));
rejects(fn()=>new TransactionDraft($address, 'bad'));
foreach (json_decode(file_get_contents(__DIR__.'/vectors.json'), true, 512, JSON_THROW_ON_ERROR) as $row) {
    same(strtolower(Contract::profile($address, $row['profile'])->encode($row['signature'], $row['args'])), $row['data']);
}
$token = Contract::profile($address, 'Token');
rejects(fn()=>$token->encode('transfer(address,uint256)', [$address, '-1']));
rejects(fn()=>$token->encode('transfer(address,uint256)', [$address, 123]));
rejects(fn()=>$token->draft($address, 'transfer(address,uint256)', [$address, '1'], '1'));
$wallet = new Wallet(str_pad('1',64,'0',STR_PAD_LEFT)); // Public test vector only.
same($wallet->address, '0x7e5f4552091a69125d5dfcb7b8c2659029395bdf');
rejects(fn()=>serialize($wallet));
rejects(fn()=>new Wallet(str_repeat('0',64)));
rejects(fn()=>new Wallet('fffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141'));
$sent = [];
$rpc = new Rpc('http://unused', function($r) use (&$sent) {
    $sent[] = $r;
    $result = match($r['method']) {
        'eth_chainId'=>'0x31524', 'eth_call'=>'0x', 'eth_estimateGas'=>'0x5208',
        'eth_gasPrice'=>'0x1', 'eth_getTransactionCount'=>'0x0', 'eth_getBalance'=>'0xde0b6b3a7640000',
        default=>throw new RuntimeException('Unexpected RPC method')
    };
    return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];
});
$client = new Client(Network::testnet(), $rpc);
$draft = TransactionDraft::del($wallet->address, $address, '0.01', 'hello');
$prepared = $client->prepare($draft);
same($prepared->gas, '23100'); same($prepared->maximumFee(), '23100');
$signed = $wallet->sign($prepared);
rejects(fn()=>new Decimal\SignedTransaction($signed->raw,75));
// Independent eth-account fixture: EIP-155, chain 202020, private key 1.
same($signed->raw, '0xf86e8001825a3c941212121212121212121212121212121212121212872386f26fc100008568656c6c6f83062a6ba0f477e63954e6ef02bf0eb34239d28696a7d6f7ac6e946f6f97ca7f45556fc0d6a02a5fc2d00044e136ff4fc6d0a52a3abea3879d86ff562ecc4e9356393e870706');
same(in_array('eth_sendRawTransaction', array_column($sent,'method'), true), false);
rejects(fn()=>$wallet->sign(new PreparedTransaction(new TransactionDraft($address,$address),202020,'0','21000','1')));
$wrong = new Client(Network::mainnet(),$rpc); rejects(fn()=>$wrong->prepare($draft));
$badRpc = new Rpc('http://unused', fn($r)=>['jsonrpc'=>'2.0','id'=>$r['id'],'error'=>['code'=>-32000,'message'=>'revert','data'=>'0xdead']]);
try {$badRpc->call('eth_call'); throw new RuntimeException('Expected RPC error');} catch (RpcException $e) {same($e->rpcData,'0xdead');}
$badRpc = new Rpc('http://unused', fn($r)=>['jsonrpc'=>'2.0','id'=>999,'result'=>null]); rejects(fn()=>$badRpc->call('eth_call'));
$broadcasts = 0;
$broadcastRpc = new Rpc('http://unused', function($r) use ($signed, &$broadcasts) {
    if ($r['method'] === 'eth_sendRawTransaction') { ++$broadcasts; same($r['params'], [$signed->raw]); }
    return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$r['method'] === 'eth_chainId' ? '0x31524' : $signed->hash];
});
same((new Client(Network::testnet(),$broadcastRpc))->broadcast($signed),$signed->hash);
same($broadcasts,1);
rejects(fn()=>(new Client(Network::mainnet(),$broadcastRpc))->broadcast($signed));
// Decode a known uint/address response and exercise the generated PHP classes.
same($token->decode('balanceOf(address)', '0x'.str_pad('7b',64,'0',STR_PAD_LEFT))[0], '123');
$methods = json_decode(file_get_contents(dirname(__DIR__).'/docs/operations.json'),true);
foreach ($methods as $row) {
    $class = 'Decimal\\Contracts\\'.$row['profile'];
    same(method_exists($class,$row['method']),true);
}
foreach (json_decode(file_get_contents(dirname(__DIR__).'/docs/abi-hashes.json'),true) as $file=>$hash) same(hash_file('sha256',dirname(__DIR__).'/abi/'.$file),$hash);

require __DIR__.'/parity.php';
require __DIR__.'/cosmos.php';
require __DIR__.'/workflows.php';

echo "OK: $count checks; no remote RPC requests or blockchain broadcasts\n";
