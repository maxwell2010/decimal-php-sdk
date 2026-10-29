<?php
declare(strict_types=1);
namespace Decimal;
final class SafeTransaction
{
    public function __construct(public readonly string $safe,public readonly int $chainId,public readonly array $message)
    {
        TransactionDraft::address($safe);if($chainId<1)throw new \InvalidArgumentException('Invalid chain');
        $this->hash();
        if(!in_array($message['operation'],['0','1'],true))throw new \InvalidArgumentException('Invalid Safe operation');
    }
    public static function types(): array { return ['SafeTx'=>array_map(static fn($f)=>['name'=>$f[0],'type'=>$f[1]],[['to','address'],['value','uint256'],['data','bytes'],['operation','uint8'],['safeTxGas','uint256'],['baseGas','uint256'],['gasPrice','uint256'],['gasToken','address'],['refundReceiver','address'],['nonce','uint256']])]; }
    public static function create(string $safe,int $chainId,TransactionDraft $call,string $nonce): self
    { return new self($safe,$chainId,['to'=>$call->to,'value'=>$call->value,'data'=>$call->data,'operation'=>'0','safeTxGas'=>'0','baseGas'=>'0','gasPrice'=>'0','gasToken'=>'0x'.str_repeat('0',40),'refundReceiver'=>'0x'.str_repeat('0',40),'nonce'=>$nonce]); }
    public function hash(): string { return TypedData::hash(self::types(),'SafeTx',['chainId'=>(string)$this->chainId,'verifyingContract'=>$this->safe],$this->message); }
    public function sign(Wallet $wallet): string { return $wallet->signHash($this->hash()); }
    public function packSignatures(array $signatures,?array $owners=null,int $threshold=1): string
    {
        if($threshold<1||count($signatures)<$threshold) throw new \InvalidArgumentException('Insufficient Safe signatures');
        $rows=[];foreach($signatures as $sig) {
            $address=Wallet::recover($this->hash(),$sig);
            if(isset($rows[$address])||($owners!==null&&!in_array($address,array_map('strtolower',$owners),true))) throw new \InvalidArgumentException('Duplicate or unauthorized Safe signer');
            $v=hexdec(substr($sig,-2));if($v<27)$v+=27;$rows[$address]=substr($sig,2,128).dechex($v);
        }
        ksort($rows,SORT_STRING);return '0x'.implode('',$rows);
    }
    public function draft(string $sender,string $packedSignatures): TransactionDraft
    {
        $inputs=array_slice(self::types()['SafeTx'],0,-1);$inputs[]=['name'=>'signatures','type'=>'bytes'];
        $abi=[['type'=>'function','name'=>'execTransaction','stateMutability'=>'payable','inputs'=>$inputs,'outputs'=>[['name'=>'success','type'=>'bool']]]];
        $args=array_map(fn($f)=>$this->message[$f['name']],array_slice(self::types()['SafeTx'],0,-1));$args[]=$packedSignatures;
        return (new Contract($this->safe,$abi))->draft($sender,'execTransaction(address,uint256,bytes,uint8,uint256,uint256,uint256,address,address,bytes)',$args);
    }
}
