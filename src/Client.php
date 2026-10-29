<?php
declare(strict_types=1);
namespace Decimal;

final class Client
{
    public readonly Rpc $rpc;
    public function __construct(public readonly Network $network, ?Rpc $rpc = null) { $this->rpc = $rpc ?? new Rpc($network->rpcUrl); }
    public function verifyChain(): void
    { if (Units::decimal($this->rpc->call('eth_chainId')) !== (string)$this->network->chainId) throw new RpcException('RPC chain ID mismatch'); }
    public function balance(string $address, string $block = 'latest'): string
    { TransactionDraft::address($address); $this->verifyChain(); return Units::decimal($this->rpc->call('eth_getBalance', [$address, $block])); }
    public function call(array $transaction, string $block = 'latest'): string
    { $this->verifyChain(); return $this->rpc->call('eth_call', [$transaction, $block]); }
    public function simulate(TransactionDraft $draft): string { return $this->call($draft->rpc(), 'pending'); }
    public function prepare(TransactionDraft $draft, int $type=0, array $accessList=[], ?string $maxFeePerGas=null, string $maxPriorityFee='0', ?string $feeCap=null): PreparedTransaction
    {
        $this->verifyChain();
        $this->simulate($draft);
        if(!in_array($type,[0,1,2],true))throw new \InvalidArgumentException('Unsupported transaction type');
        if($type===2 && $maxFeePerGas===null)throw new \InvalidArgumentException('Explicit maximum dynamic fee required');
        $request=$draft->rpc();
        if($type!==0)$request['accessList']=$accessList;
        if($type===2){$request['maxFeePerGas']=Units::hex($maxFeePerGas);$request['maxPriorityFeePerGas']=Units::hex($maxPriorityFee);}
        $gas = Units::decimal($this->rpc->call('eth_estimateGas', [$request]));
        $gas = bcdiv(bcadd(bcmul($gas, '110', 0), '99', 0), '100', 0);
        $price = $maxFeePerGas ?? Units::decimal($this->rpc->call('eth_gasPrice'));
        $nonce = Units::decimal($this->rpc->call('eth_getTransactionCount', [$draft->from, 'pending']));
        $fee = bcmul($gas, $price, 0);
        if($feeCap!==null && bccomp($fee,Units::uint($feeCap), 0)>0)throw new \RuntimeException('Maximum fee exceeds application cap');
        if (bccomp($this->balance($draft->from, 'pending'), bcadd($draft->value, $fee, 0), 0) < 0) throw new \RuntimeException('Insufficient balance for amount and maximum fee');
        return new PreparedTransaction($draft, $this->network->chainId, $nonce, $gas, $price,$type,$accessList,$maxPriorityFee);
    }
    public function broadcast(SignedTransaction $signed): string
    {
        if ($signed->chainId !== $this->network->chainId) throw new \InvalidArgumentException('Signed chain mismatch');
        $this->verifyChain();
        $hash = $this->rpc->call('eth_sendRawTransaction', [$signed->raw]);
        if (strtolower($hash) !== strtolower($signed->hash)) throw new RpcException('Broadcast hash mismatch');
        return $hash;
    }
    public function receipt(string $hash): ?array
    {
        if (!preg_match('/^0x[0-9a-fA-F]{64}$/D', $hash)) throw new \InvalidArgumentException('Invalid transaction hash');
        $this->verifyChain(); return $this->rpc->call('eth_getTransactionReceipt', [$hash]);
    }
    public function waitReceipt(string $hash, int $timeoutSeconds = 120, int $pollMilliseconds = 1000): array
    {
        if ($timeoutSeconds < 1 || $pollMilliseconds < 1) throw new \InvalidArgumentException('Invalid polling limits');
        $deadline = microtime(true) + $timeoutSeconds;
        do {
            $receipt = $this->receipt($hash);
            if ($receipt !== null) {
                if (($receipt['status'] ?? null) !== '0x1') throw new RpcException('Transaction reverted or missing status', 0, $receipt);
                return $receipt;
            }
            usleep($pollMilliseconds * 1000);
        } while (microtime(true) < $deadline);
        throw new \RuntimeException('Receipt timeout; transaction may still be pending');
    }
}
