<?php
declare(strict_types=1);
namespace Decimal;
/** Reviewed Decimal NFT permits. 1155 approval is operator-wide, never amount-limited. */
final class NftPermitService
{
 private Contract $contract;
 public function __construct(private readonly Client $client,public readonly string $address,public readonly int $standard,public readonly string $version)
 {
  if(!in_array($standard,[721,1155],true)||trim($version)==='')throw new \InvalidArgumentException('Explicit NFT standard and domain version required');
  $abi=[];foreach([['name',[],'string'],['ownerOf',['uint256'],'address'],['nonces',[$standard===721?'uint256':'address'],'uint256'],['DOMAIN_SEPARATOR',[],'bytes32']] as [$name,$inputs,$output])$abi[]=['type'=>'function','name'=>$name,'stateMutability'=>'view','inputs'=>array_map(fn($t)=>['name'=>'','type'=>$t],$inputs),'outputs'=>[['name'=>'','type'=>$output]]];
  $this->contract=new Contract($address,$abi);
 }
 public static function typedData(int $standard,string $collection,string $name,string $version,string $owner,string $spender,string $nonce,string $deadline,string $chainId,?string $tokenId=null): array
 {
  if(!in_array($standard,[721,1155],true)||trim($name)===''||trim($version)==='')throw new \InvalidArgumentException('Invalid NFT permit schema');
  $owner=TransactionDraft::address($owner);$spender=TransactionDraft::address($spender);TransactionDraft::address($collection);
  if($owner==='0x'.str_repeat('0',40)||$owner===$spender)throw new \InvalidArgumentException('Invalid permit owner/spender');
  foreach([$nonce,$deadline,$chainId] as $n)Units::uint($n);if($chainId==='0')throw new \InvalidArgumentException('Positive chain ID required');
  $fields=[['name'=>'spender','type'=>'address']];$message=['spender'=>$spender];
  if($standard===721){if($tokenId===null)throw new \InvalidArgumentException('Token ID required');Units::uint($tokenId);$fields[]=['name'=>'tokenId','type'=>'uint256'];$message['tokenId']=$tokenId;}elseif($tokenId!==null)throw new \InvalidArgumentException('DRC1155 permit is operator-wide, not per-ID');
  foreach(['nonce'=>$nonce,'deadline'=>$deadline] as $key=>$n){$fields[]=['name'=>$key,'type'=>'uint256'];$message[$key]=$n;}
  $types=['Permit'=>$fields];$domain=['name'=>$name,'version'=>$version,'chainId'=>$chainId,'verifyingContract'=>$collection];$preimage=TypedData::preimage($types,'Permit',$domain,$message);
  return ['types'=>$types,'domain'=>$domain,'message'=>$message,'domainSeparator'=>'0x'.bin2hex(substr($preimage,2,32)),'digest'=>TypedData::hash($types,'Permit',$domain,$message),'owner'=>$owner,'deadline'=>$deadline];
 }
 public function prepare(string $owner,string $spender,string $deadline,?string $tokenId=null,string $block='latest'): array
 {
  Units::uint($deadline);if(bccomp($deadline,(string)time(),0)<=0)throw new \InvalidArgumentException('Future permit deadline required');
  // Validate inputs before any provider read.
  self::typedData($this->standard,$this->address,'pending',$this->version,$owner,$spender,'0',$deadline,(string)$this->client->network->chainId,$tokenId);
  $this->client->verifyChain();
  if($this->standard===721&&TransactionDraft::address($this->contract->call($this->client,'ownerOf(uint256)',[$tokenId],$block)[0])!==TransactionDraft::address($owner))throw new \RuntimeException('Signer is not current NFT owner');
  $name=$this->contract->call($this->client,'name()',[],$block)[0];
  $nonce=$this->contract->call($this->client,$this->standard===721?'nonces(uint256)':'nonces(address)',[$this->standard===721?$tokenId:$owner],$block)[0];
  $typed=self::typedData($this->standard,$this->address,$name,$this->version,$owner,$spender,$nonce,$deadline,(string)$this->client->network->chainId,$tokenId);
  if(strtolower($this->contract->call($this->client,'DOMAIN_SEPARATOR()',[],$block)[0])!==strtolower($typed['domainSeparator']))throw new \RuntimeException('NFT permit domain mismatch');return $typed;
 }
 public function sign(Wallet $wallet,string $spender,string $deadline,?string $tokenId=null,string $block='latest'): array
 {
  $typed=$this->prepare($wallet->address,$spender,$deadline,$tokenId,$block);
  if($this->standard===721&&!in_array($this->client->rpc->call('eth_getCode',[$wallet->address,$block]),['0x','0x0'],true))throw new \RuntimeException('Contract-owner permit signing unsupported');
  return $this->signature($wallet->signTypedData($typed['types'],'Permit',$typed['domain'],$typed['message']))+['prepared'=>$typed];
 }
 /** Re-read nonce/domain/ownership before accepting an external EOA signature. */
 public function verifyExternal(string $owner,string $spender,string $deadline,string $signature,?string $tokenId=null,string $block='latest'): array
 {
  $parts=$this->signature($signature);$typed=$this->prepare($owner,$spender,$deadline,$tokenId,$block);
  if($this->standard===721&&!in_array($this->client->rpc->call('eth_getCode',[$owner,$block]),['0x','0x0'],true))throw new \RuntimeException('Contract-owner permit verification unsupported');
  if(Wallet::recover($typed['digest'],$signature)!==TransactionDraft::address($owner))throw new \RuntimeException('NFT permit signature owner mismatch');return $parts+['prepared'=>$typed];
 }
 private function signature(string $signature): array
 {
  if(!preg_match('/^0x[0-9a-fA-F]{130}$/D',$signature)||!in_array(substr(strtolower($signature),-2),['1b','1c'],true))throw new \InvalidArgumentException('Expected EOA r/s/v signature');
  $r=strtolower(substr($signature,2,64));$s=strtolower(substr($signature,66,64));
  if($r===str_repeat('0',64)||$s===str_repeat('0',64)||strcmp($r,'fffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141')>=0||strcmp($s,'7fffffffffffffffffffffffffffffff5d576e7357a4501ddfe92f46681b20a0')>0)throw new \InvalidArgumentException('Invalid or noncanonical permit scalar');
  return ['signature'=>$signature,'v'=>(string)hexdec(substr($signature,-2)),'r'=>'0x'.$r,'s'=>'0x'.$s];
 }
 /** Raw unsigned ABI call. Fresh signature validity is checked separately by verifyExternal. */
 public function draft(string $executor,string $owner,string $spender,string $deadline,string $v,string $r,string $s,?string $tokenId=null): TransactionDraft
 {
  if(!in_array($v,['27','28'],true))throw new \InvalidArgumentException('Expected EOA v');
  if(!preg_match('/^0x[0-9a-fA-F]{64}$/D',$r)||!preg_match('/^0x[0-9a-fA-F]{64}$/D',$s))throw new \InvalidArgumentException('Expected bytes32 r/s');
  $this->signature('0x'.substr($r,2).substr($s,2).dechex((int)$v));
  $types=$this->standard===721?['address','uint256','uint256','uint8','bytes32','bytes32']:['address','address','uint256','uint8','bytes32','bytes32'];
  $args=$this->standard===721?[$spender,$tokenId,$deadline,$v,$r,$s]:[$owner,$spender,$deadline,$v,$r,$s];
  $c=new Contract($this->address,[['type'=>'function','name'=>'permit','stateMutability'=>'nonpayable','inputs'=>array_map(fn($t)=>['name'=>'','type'=>$t],$types),'outputs'=>[]]]);return $c->draft($executor,'permit('.implode(',',$types).')',$args);
 }
}
