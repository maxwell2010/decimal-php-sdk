<?php
declare(strict_types=1);
namespace Decimal;

final class Wallet
{
    private string $privateKey;
    public readonly string $address;
    public function __construct(#[\SensitiveParameter] string $privateKey)
    {
        $privateKey = preg_replace('/^0x/', '', $privateKey);
        if (!preg_match('/^[a-fA-F0-9]{64}$/D', $privateKey) || preg_match('/^0+$/D', $privateKey) || strcmp(strtolower($privateKey), 'fffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141') >= 0) throw new \InvalidArgumentException('Invalid secp256k1 key');
        $this->privateKey = $privateKey;
        $pub = (new \Elliptic\EC('secp256k1'))->keyFromPrivate($privateKey, 'hex')->getPublic(false, 'hex');
        $this->address = '0x'.substr(\kornrunner\Keccak::hash(hex2bin(substr($pub,2)),256),24);
    }
    public function sign(PreparedTransaction $prepared): SignedTransaction
    {
        if (strtolower($prepared->draft->from) !== strtolower($this->address)) throw new \InvalidArgumentException('Signer does not match sender');
        $rlp = new Rlp();
        $quantity = static fn(string $n): string => $n === '0' ? '' : Units::hex($n);
        $fields = [$quantity($prepared->nonce), $quantity($prepared->gasPrice), $quantity($prepared->gas), $prepared->draft->to, $quantity($prepared->draft->value), $prepared->draft->data === '0x' ? '' : $prepared->draft->data];
        $prefix='';
        if ($prepared->type !== 0) {
            $prefix=$prepared->type===1?'01':'02';
            $access=array_map(static fn($r)=>[$r['address'],$r['storageKeys']],$prepared->accessList);
            $fields=[Units::hex((string)$prepared->chainId),$quantity($prepared->nonce),...($prepared->type===2?[$quantity($prepared->maxPriorityFee),$quantity($prepared->gasPrice)]:[$quantity($prepared->gasPrice)]),$quantity($prepared->gas),$prepared->draft->to,$quantity($prepared->draft->value),$prepared->draft->data==='0x'?'':$prepared->draft->data,$access];
        }
        $unsigned=$prepared->type===0?[...$fields, Units::hex((string)$prepared->chainId), '', '']:$fields;
        $hash = \kornrunner\Keccak::hash(hex2bin($prefix.$rlp->encode($unsigned)), 256);
        $ec = new \Elliptic\EC('secp256k1');
        $signature = $ec->keyFromPrivate($this->privateKey, 'hex')->sign($hash, ['canonical'=>true]);
        $v = $prepared->type===0?bcadd(bcmul((string)$prepared->chainId, '2', 0), (string)(35 + $signature->recoveryParam), 0):(string)$signature->recoveryParam;
        return new SignedTransaction('0x'.$prefix.$rlp->encode([...$fields, $quantity($v), '0x'.$signature->r->toString(16), '0x'.$signature->s->toString(16)]), $prepared->chainId);
    }
    public function signHash(string $hash): string
    {
        if(!preg_match('/^0x[0-9a-fA-F]{64}$/D',$hash)) throw new \InvalidArgumentException('Expected 32-byte digest');
        $sig=(new \Elliptic\EC('secp256k1'))->keyFromPrivate($this->privateKey,'hex')->sign(substr($hash,2),['canonical'=>true]);
        return '0x'.str_pad($sig->r->toString(16),64,'0',STR_PAD_LEFT).str_pad($sig->s->toString(16),64,'0',STR_PAD_LEFT).dechex(27+$sig->recoveryParam);
    }
    public function publicKey(): string
    { return '0x'.(new \Elliptic\EC('secp256k1'))->keyFromPrivate($this->privateKey,'hex')->getPublic(true,'hex'); }
    public function signMessage(string $message): string { return $this->signHash('0x'.\kornrunner\Keccak::hash("\x19Ethereum Signed Message:\n".strlen($message).$message,256)); }
    public function signTypedData(array $types,string $primaryType,array $domain,array $message): string
    { return $this->signHash(TypedData::hash($types,$primaryType,$domain,$message)); }
    public static function recover(string $hash,string $signature): string
    {
        if(!preg_match('/^0x[0-9a-fA-F]{64}$/D',$hash)||!preg_match('/^0x[0-9a-fA-F]{130}$/D',$signature)) throw new \InvalidArgumentException('Invalid hash/signature');
        $v=hexdec(substr($signature,-2)); if($v>=27) $v-=27;if($v>1) throw new \InvalidArgumentException('Invalid recovery ID');
        $pub=(new \Elliptic\EC('secp256k1'))->recoverPubKey(substr($hash,2),['r'=>substr($signature,2,64),'s'=>substr($signature,66,64)],$v)->encode('hex',false);
        return '0x'.substr(\kornrunner\Keccak::hash(hex2bin(substr($pub,2)),256),24);
    }
    public function __debugInfo(): array { return ['address'=>$this->address]; }
    public function __serialize(): array { throw new \LogicException('Wallet serialization disabled'); }
}
