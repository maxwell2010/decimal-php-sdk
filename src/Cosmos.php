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
        if($signers===[]||!array_is_list($signers))throw new \InvalidArgumentException('Signers required');$infos=[];$seen=[];
        foreach($signers as $s){
            if(!preg_match('/^0x0[23][0-9a-fA-F]{64}$/D',$s['publicKey']))throw new \InvalidArgumentException('Compressed public key required');
            if(isset($seen[strtolower($s['publicKey'])]))throw new \InvalidArgumentException('Duplicate Cosmos signer');$seen[strtolower($s['publicKey'])]=true;
            $pub=$this->codec->encode('ethermint.crypto.v1.ethsecp256k1.PubKey',['key'=>$s['publicKey']]);
            $infos[]=['public_key'=>['type_url'=>'/ethermint.crypto.v1.ethsecp256k1.PubKey','value'=>'0x'.bin2hex($pub)],'mode_info'=>['single'=>['mode'=>'1']],'sequence'=>$s['sequence']];
        }
        foreach($fee as $coin)if(!is_string($coin['denom']??null)||$coin['denom']===''||!is_string($coin['amount']??null)||!preg_match('/^[0-9]+$/D',$coin['amount']))throw new \InvalidArgumentException('Invalid fee coin');
        return $this->codec->encode('cosmos.tx.v1beta1.AuthInfo',['signer_infos'=>$infos,'fee'=>['amount'=>$fee,'gas_limit'=>$gas]]);
    }
    public function signDoc(string $body,string $auth,string $chainId,string $accountNumber): string
    {
        if($chainId==='')throw new \InvalidArgumentException('Invalid Cosmos chain');
        $this->codec->decode('cosmos.tx.v1beta1.TxBody',$body);
        $this->codec->decode('cosmos.tx.v1beta1.AuthInfo',$auth);
        return $this->codec->encode('cosmos.tx.v1beta1.SignDoc',['body_bytes'=>'0x'.bin2hex($body),'auth_info_bytes'=>'0x'.bin2hex($auth),'chain_id'=>$chainId,'account_number'=>$accountNumber]);
    }
    /** Assemble the envelope only after checking body/auth wire shape and signer count. Signatures are 0x-prefixed hex. */
    public function txRaw(string $body,string $auth,array $signatures): string
    {
        $this->codec->decode('cosmos.tx.v1beta1.TxBody',$body);
        $decoded=$this->codec->decode('cosmos.tx.v1beta1.AuthInfo',$auth);
        if(!array_is_list($signatures)||count($signatures)===0||count($signatures)!==count($decoded['signer_infos']??[]))throw new \InvalidArgumentException('Cosmos signature/signer count mismatch');
        foreach($signatures as $signature)if(!is_string($signature)||!preg_match('/^0x[0-9a-fA-F]{128}0[01]$/D',$signature))throw new \InvalidArgumentException('Invalid 65-byte Ethermint signature shape');
        return $this->codec->encode('cosmos.tx.v1beta1.TxRaw',['body_bytes'=>'0x'.bin2hex($body),'auth_info_bytes'=>'0x'.bin2hex($auth),'signatures'=>$signatures]);
    }
    /** Validate the signed envelope before transport; this does not prove signature validity or node acceptance. */
    public function validateTxRaw(string $raw): void
    {
        $tx=$this->codec->decode('cosmos.tx.v1beta1.TxRaw',$raw);
        $body=hex2bin(substr($tx['body_bytes']??'0x',2));$auth=hex2bin(substr($tx['auth_info_bytes']??'0x',2));
        $decoded=$this->codec->decode('cosmos.tx.v1beta1.TxBody',$body);
        if(count($decoded['messages']??[])===0)throw new \InvalidArgumentException('Cosmos transaction messages required');
        $this->txRaw($body,$auth,$tx['signatures']??[]);
    }
    /** Signers must be supplied in required message-signer order, not address order. */
    public function sign(string $body,string $chainId,array $signers,array $fee,string $gas): array
    {
        $infos=[];foreach($signers as $s){if(!($s['wallet']??null) instanceof Wallet)throw new \InvalidArgumentException('Wallet required');$infos[]=['publicKey'=>$s['wallet']->publicKey(),'sequence'=>$s['sequence']];}
        $auth=$this->auth($infos,$fee,$gas);$signatures=[];
        foreach($signers as $s){$doc=$this->signDoc($body,$auth,$chainId,$s['accountNumber']);$digest=strlen($doc)===32?'0x'.bin2hex($doc):'0x'.\kornrunner\Keccak::hash($doc,256);$sig=$s['wallet']->signHash($digest);$sig=substr($sig,0,-2).sprintf('%02x',hexdec(substr($sig,-2))-27);$signatures[]=$sig;}
        $raw=$this->txRaw($body,$auth,$signatures);
        return ['raw'=>'0x'.bin2hex($raw),'base64'=>base64_encode($raw),'hash'=>strtoupper(hash('sha256',$raw)),'body'=>'0x'.bin2hex($body),'auth'=>'0x'.bin2hex($auth)];
    }
    /** Pinned GetSigners rules, preserving first message occurrence. */
    public function requiredSigners(string $body,string $feePayer=''): array
    {
        $rules=json_decode(file_get_contents(dirname(__DIR__).'/resources/cosmos/signer-rules.json'),true,512,JSON_THROW_ON_ERROR);
        $decoded=$this->codec->decode('cosmos.tx.v1beta1.TxBody',$body);$messages=$decoded['messages']??[];
        if(!$messages)throw new \InvalidArgumentException('Messages required');
        $seen=[];$result=[];
        foreach($messages as $message){
            $type=$message['type_url']??'';$rule=$rules[$type]??throw new \InvalidArgumentException('Unsupported Cosmos signer message');
            $payload=$this->codec->decode($type,hex2bin(substr($message['value']??'0x',2)));
            $address='0x'.bin2hex(Address::fromBech32Bytes($payload[$rule['field']]??'',$rule['hrp']));
            if(!isset($seen[$address])){$seen[$address]=true;$result[]=$address;}
        }
        if($feePayer!==''){
            $payer='0x'.bin2hex(Address::fromBech32Bytes($feePayer));
            if(!isset($seen[$payer]))$result[]=$payer;
        }
        return $result;
    }
    /** Binds signers to source-backed message addresses before any signing occurs. */
    public function signChecked(string $body,string $chainId,array $signers,array $fee,string $gas): array
    {
        if(!array_is_list($signers))throw new \InvalidArgumentException('Signers must be an ordered list');
        $required=$this->requiredSigners($body);
        if(count($required)!==count($signers))throw new \InvalidArgumentException('Cosmos signer count mismatch');
        foreach($signers as $i=>$signer)if(!($signer['wallet']??null) instanceof Wallet||strtolower($signer['wallet']->address)!==$required[$i])throw new \InvalidArgumentException('Cosmos signer order/address mismatch');
        return $this->sign($body,$chainId,$signers,$fee,$gas);
    }
    public function signValidated(string $body,string $chainId,array $signers,array $fee,string $gas): array
    {
        $decoded=$this->codec->decode('cosmos.tx.v1beta1.TxBody',$body);
        foreach($decoded['messages']??[] as $message){
            $type=$message['type_url']??'';$payload=$this->codec->decode($type,hex2bin(substr($message['value']??'0x',2)));
            CosmosValidation::validate($type,$payload);
        }
        return $this->signChecked($body,$chainId,$signers,$fee,$gas);
    }
}
