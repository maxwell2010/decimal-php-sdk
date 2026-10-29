<?php
declare(strict_types=1);
namespace Decimal;
final class Cosmos
{
    public function __construct(private readonly Protobuf $codec=new Protobuf()) {}
    public function body(array $messages,string $memo='',string $timeoutHeight='0'): string
    {
        if($messages===[])throw new \InvalidArgumentException('Messages required');
        return $this->codec->encode('cosmos.tx.v1beta1.TxBody',['messages'=>$messages,'memo'=>$memo,'timeout_height'=>$timeoutHeight]);
    }
    public function auth(array $signers,array $fee,string $gas): string
    {
        if($signers===[])throw new \InvalidArgumentException('Signers required');$infos=[];$seen=[];
        foreach($signers as $s){
            if(!preg_match('/^0x0[23][0-9a-fA-F]{64}$/D',$s['publicKey']))throw new \InvalidArgumentException('Compressed public key required');
            if(isset($seen[strtolower($s['publicKey'])]))throw new \InvalidArgumentException('Duplicate Cosmos signer');$seen[strtolower($s['publicKey'])]=true;
            $pub=$this->codec->encode('ethermint.crypto.v1.ethsecp256k1.PubKey',['key'=>$s['publicKey']]);
            $infos[]=['public_key'=>['type_url'=>'/ethermint.crypto.v1.ethsecp256k1.PubKey','value'=>'0x'.bin2hex($pub)],'mode_info'=>['single'=>['mode'=>'1']],'sequence'=>$s['sequence']];
        }
        foreach($fee as $coin)if(!preg_match('/^[a-zA-Z][a-zA-Z0-9\/:._-]{2,127}$/D',$coin['denom'])||!preg_match('/^[1-9][0-9]*$/D',$coin['amount']))throw new \InvalidArgumentException('Invalid fee coin');
        return $this->codec->encode('cosmos.tx.v1beta1.AuthInfo',['signer_infos'=>$infos,'fee'=>['amount'=>$fee,'gas_limit'=>$gas]]);
    }
    public function signDoc(string $body,string $auth,string $chainId,string $accountNumber): string
    {
        if($chainId===''||strlen($chainId)>128)throw new \InvalidArgumentException('Invalid Cosmos chain');
        return $this->codec->encode('cosmos.tx.v1beta1.SignDoc',['body_bytes'=>'0x'.bin2hex($body),'auth_info_bytes'=>'0x'.bin2hex($auth),'chain_id'=>$chainId,'account_number'=>$accountNumber]);
    }
    /** Signers must be supplied in required message-signer order, not address order. */
    public function sign(string $body,string $chainId,array $signers,array $fee,string $gas): array
    {
        $infos=[];foreach($signers as $s){if(!($s['wallet']??null) instanceof Wallet)throw new \InvalidArgumentException('Wallet required');$infos[]=['publicKey'=>$s['wallet']->publicKey(),'sequence'=>$s['sequence']];}
        $auth=$this->auth($infos,$fee,$gas);$signatures=[];
        foreach($signers as $s){$doc=$this->signDoc($body,$auth,$chainId,$s['accountNumber']);$digest=strlen($doc)===32?'0x'.bin2hex($doc):'0x'.\kornrunner\Keccak::hash($doc,256);$sig=$s['wallet']->signHash($digest);$sig=substr($sig,0,-2).sprintf('%02x',hexdec(substr($sig,-2))-27);$signatures[]=$sig;}
        $raw=$this->codec->encode('cosmos.tx.v1beta1.TxRaw',['body_bytes'=>'0x'.bin2hex($body),'auth_info_bytes'=>'0x'.bin2hex($auth),'signatures'=>$signatures]);
        return ['raw'=>'0x'.bin2hex($raw),'base64'=>base64_encode($raw),'hash'=>strtoupper(hash('sha256',$raw)),'body'=>'0x'.bin2hex($body),'auth'=>'0x'.bin2hex($auth)];
    }
}
