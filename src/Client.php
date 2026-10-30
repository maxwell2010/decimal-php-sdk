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
    public function resolveSystemContract(string $slug,string $block='latest'): string
    {
        if(trim($slug)==='')throw new \InvalidArgumentException('Contract slug required');
        $address=Contract::profile($this->network->contractCenter,'ContractCenter')->call($this,'getContractAddress(string)',[$slug],$block)[0];
        if(TransactionDraft::address($address)==='0x'.str_repeat('0',40))throw new \RuntimeException('System contract is not registered');
        return $address;
    }
    /** Return a snapshot; never mutate the caller's legacy/unified routing configuration. */
    public function resolveSystemContracts(string $block='latest'): array
    {
        if($block==='latest'){$this->verifyChain();$block=$this->rpc->call('eth_blockNumber');Units::decimal($block);}
        $out=['block'=>$block];foreach(['delegation','token-center','nft-center','wdel'] as $slug)$out[$slug]=$this->resolveSystemContract($slug,$block);return $out;
    }
    public function estimateFee(TransactionDraft $draft,bool $exact=false): FeeQuote
    {
        $this->verifyChain();$price=Units::decimal($this->rpc->call('eth_gasPrice'));$request=$draft->rpc()+['gasPrice'=>Units::hex($price)];
        $gas=Units::decimal($this->rpc->call('eth_estimateGas',[$request]));
        $limit=$exact?$gas:bcdiv(bcadd(bcmul($gas,'110',0),'99',0),'100',0);
        return new FeeQuote($draft,$gas,$limit,$price,$this->balance($draft->from,'pending'),$exact);
    }
    public function transactionResult(string $hash): TransactionResult
    { return TransactionResult::fromReceipt($hash,$this->receipt($hash)); }
    public function prepare(TransactionDraft $draft, int $type=0, array $accessList=[], ?string $maxFeePerGas=null, string $maxPriorityFee='0', ?string $feeCap=null, ?NonceManager $nonces=null, ?string $baseFeeEstimate=null, ?CancellationToken $cancellation=null): PreparedTransaction
    {
        $cancellation?->throwIfCancelled();
        if(!in_array($type,[0,1,2],true))throw new \InvalidArgumentException('Unsupported transaction type');
        if($type===2 && $maxFeePerGas===null)throw new \InvalidArgumentException('Explicit maximum dynamic fee required');
        new PreparedTransaction($draft,$this->network->chainId,'0','1',$maxFeePerGas??'0',$type,$accessList,$maxPriorityFee,null,$baseFeeEstimate);
        if($feeCap!==null) Units::uint($feeCap);
        $this->verifyChain();
        $cancellation?->throwIfCancelled();
        $price = $maxFeePerGas ?? Units::decimal($this->rpc->call('eth_gasPrice'));
        $cancellation?->throwIfCancelled();
        $request=$draft->rpc();
        if($type!==0)$request['accessList']=$accessList;
        if($type===2){$request['maxFeePerGas']=Units::hex($maxFeePerGas);$request['maxPriorityFeePerGas']=Units::hex($maxPriorityFee);}
        else $request['gasPrice']=Units::hex($price);
        $this->call($request,'pending');
        $cancellation?->throwIfCancelled();
        $gas = Units::decimal($this->rpc->call('eth_estimateGas', [$request]));
        $estimate=$gas;
        $gas = bcdiv(bcadd(bcmul($gas, '110', 0), '99', 0), '100', 0);
        $cancellation?->throwIfCancelled();
        $nonce = Units::decimal($this->rpc->call('eth_getTransactionCount', [$draft->from, 'pending']));
        $fee = bcmul($gas, $price, 0);
        if($feeCap!==null && bccomp($fee,Units::uint($feeCap), 0)>0)throw new \RuntimeException('Maximum fee exceeds application cap');
        if (bccomp($this->balance($draft->from, 'pending'), bcadd($draft->value, $fee, 0), 0) < 0) throw new \RuntimeException('Insufficient balance for amount and maximum fee');
        $cancellation?->throwIfCancelled();
        new PreparedTransaction($draft,$this->network->chainId,$nonce,$gas,$price,$type,$accessList,$maxPriorityFee,$estimate,$baseFeeEstimate);
        if($nonces!==null) $nonce=$nonces->reserve($this->network->chainId,$draft->from,$nonce);
        return new PreparedTransaction($draft, $this->network->chainId, $nonce, $gas, $price,$type,$accessList,$maxPriorityFee,$estimate,$baseFeeEstimate);
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
        $this->verifyChain();
        $receipt=$this->rpc->call('eth_getTransactionReceipt', [$hash]);
        if($receipt===null) return null;
        if(!is_array($receipt)||!is_string($receipt['transactionHash']??null)||strtolower($receipt['transactionHash'])!==strtolower($hash)||!in_array($receipt['status']??null,['0x0','0x1'],true)) throw new RpcException('Malformed receipt or transaction hash mismatch');
        return $receipt;
    }
    public function waitReceipt(string $hash, int $timeoutSeconds = 120, int $pollMilliseconds = 1000, ?CancellationToken $cancellation=null): array
    {
        if ($timeoutSeconds < 1 || $pollMilliseconds < 1) throw new \InvalidArgumentException('Invalid polling limits');
        $deadline = microtime(true) + $timeoutSeconds;
        do {
            $cancellation?->throwIfCancelled();
            $receipt = $this->receipt($hash);
            if ($receipt !== null) {
                if (($receipt['status'] ?? null) !== '0x1') throw new RpcException('Transaction reverted or missing status', 0, $receipt);
                return $receipt;
            }
            $wake=min($deadline,microtime(true)+$pollMilliseconds/1000);
            do {
                $cancellation?->throwIfCancelled();
                $remaining=$wake-microtime(true);
                if($remaining>0) usleep((int)min(100000,ceil($remaining*1000000)));
            } while(microtime(true)<$wake);
        } while (microtime(true) < $deadline);
        throw new \RuntimeException('Receipt timeout; transaction may still be pending');
    }
}
