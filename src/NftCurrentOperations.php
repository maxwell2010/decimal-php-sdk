<?php
declare(strict_types=1);
namespace Decimal;
/** Current Python request variants and read-before-build economic checks. No signing or sends. */
final class NftCurrentOperations
{
 private NftOperations $raw;
 public function __construct(private readonly Client $client,private readonly string $address,private readonly int $standard,private readonly string $center)
 { $this->raw=new NftOperations($client,$address,$standard);TransactionDraft::address($center); }
 public function approve(string $sender,string $operator,string $id,string $block='latest'): TransactionDraft { return $this->raw->approve($sender,$operator,$id,$block); }
 public function setApprovalForAll(string $sender,string $operator,bool $approved): TransactionDraft { return $this->raw->setApprovalForAll($sender,$operator,$approved); }
 public function burn(string $sender,string $id,string $amount='1',string $block='latest'): TransactionDraft { return $this->raw->burn($sender,$id,$amount,$block); }
 public function disableMint(string $sender): TransactionDraft { return $this->raw->disableMint($sender); }
 public function addDelReserve(string $sender,string $id,string $reserveWei): TransactionDraft { return $this->raw->addDelReserve($sender,$id,$reserveWei); }
 public function delegate(string $sender,string $delegation,string $validator,string $id,string $amount='1',bool $autoApprove=false,string $profile='Delegation20261030',string $block='latest'): array { return $this->raw->delegate($sender,$delegation,$validator,$id,$amount,$autoApprove,$profile,$block); }
 public function hold(string $sender,string $delegation,string $validator,string $id,string $holdTimestamp,string $amount='1',bool $autoApprove=false,string $profile='Delegation20261030',string $block='latest'): array { return $this->raw->hold($sender,$delegation,$validator,$id,$holdTimestamp,$amount,$autoApprove,$profile,$block); }
 public function transferBatch1155(string $sender,string $recipient,array $ids,array $amounts,string $data='0x',string $block='latest'): TransactionDraft { return $this->raw->transferBatch1155($sender,$recipient,$ids,$amounts,$data,$block); }
 public function transferFrom(string $executor,string $owner,string $recipient,string $id,string $amount='1',?string $data=null,bool $safe=true): TransactionDraft { return $this->raw->transferFrom($executor,$owner,$recipient,$id,$amount,$data,$safe); }
 public function transfer(string $sender,string $recipient,string $id,string $amount='1',string $data='0x',string $block='latest'): TransactionDraft
 { $s=new NftService($this->client,$this->address);return $this->standard===721?$s->transfer721($sender,$recipient,$id,$block):$s->transfer1155($sender,$recipient,$id,$amount,$data,$block); }
 public function createCollection(string $sender,string $creator,string $symbol,string $name,string $uri,bool $refundable=false,bool $burnable=true): TransactionDraft
 { foreach([$symbol,$name,$uri] as $text)self::requireText($text);return DeployedNft::createCollection($this->center,$this->standard,$sender,$creator,$symbol,$name,$uri,$refundable,$burnable); }
 public function createReservelessCollection(string $sender,string $creator,string $symbol,string $name,string $uri,bool $burnable=true): TransactionDraft
 { foreach([$symbol,$name,$uri] as $text)self::requireText($text);return NftOperations::createReservelessCollection($this->center,$this->standard,$sender,$creator,$symbol,$name,$uri,false,$burnable); }
 public function legacyStaking(string $deployment): NftLegacyStaking { return new NftLegacyStaking($this->client,$deployment); }
 public function unifiedStaking(string $deployment): NftStakingReader { return new NftStakingReader($this->client,$deployment); }
 public function ownerOf(string $id,string $block='latest'): string { return (new NftReader($this->client,$this->address,$this->standard))->ownerOf($id,$block); }
 public function balanceOf(string $owner,?string $id=null,string $block='latest'): string { return (new NftReader($this->client,$this->address,$this->standard))->balanceOf($owner,$id,$block); }
 public function isApprovedForAll(string $owner,string $operator,string $block='latest'): bool { return (new NftReader($this->client,$this->address,$this->standard))->isApprovedForAll($owner,$operator,$block); }
 private static function requireText(string $text): void
 {
  if(preg_match('//u',$text)!==1)throw new \InvalidArgumentException('Invalid UTF-8 NFT text');
  if(preg_match('/\A[\p{Z}\x{0009}-\x{000D}\x{001C}-\x{001F}\x{0085}]*\z/u',$text)===1)throw new \InvalidArgumentException('Nonblank NFT text required');
 }
 private static function permit(array $p): array
 {
  if(array_diff(array_keys($p),['deadline','v','r','s'])||count($p)!==4)throw new \InvalidArgumentException('Canonical permit fields required');
  foreach(['deadline','v'] as $key)Units::uint($p[$key]);if($p['deadline']==='0')throw new \InvalidArgumentException('Positive permit deadline required');if(!in_array($p['v'],['27','28'],true))throw new \InvalidArgumentException('EOA permit v required');
  foreach(['r','s'] as $key)if(!preg_match('/^0x[0-9a-fA-F]{64}$/D',$p[$key]))throw new \InvalidArgumentException('Permit bytes32 required');
  if($p['r']==='0x'.str_repeat('0',64)||$p['s']==='0x'.str_repeat('0',64)||strcmp(strtolower(substr($p['r'],2)),'fffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141')>=0||strcmp(strtolower(substr($p['s'],2)),'7fffffffffffffffffffffffffffffff5d576e7357a4501ddfe92f46681b20a0')>0)throw new \InvalidArgumentException('Canonical nonzero permit scalars required');
  return [$p['deadline'],$p['v'],$p['r'],$p['s']];
 }
 public function createdCollections(array $receipt): array
 { $out=[];foreach(NftReceipts::created($receipt,$this->center) as $args){if(!in_array($args['nftType'],['0','1'],true))throw new \RuntimeException('Unsupported NFT collection type');$m=$args['nft'];$out[]=['address'=>$args['tokenAddress'],'standard'=>$args['nftType']==='0'?721:1155,'creator'=>$m[0],'symbol'=>$m[1],'name'=>$m[2],'contractUri'=>$m[3],'refundable'=>$m[4],'burnable'=>$m[5]];}return $out; }
 public function collectionState(string $block='latest'): array
 { $s=Contract::profile($this->center,'TestnetNFTCenter20261029')->call($this->client,'getNFTState(address)',[$this->address],$block)[0];if(!in_array($s[1],['0','1'],true))throw new \RuntimeException('Unsupported NFT standard');return ['active'=>$s[0],'standard'=>$s[1]==='0'?721:1155,'withReserve'=>$s[2]]; }
 public function reserve(string $id,string $block='latest'): array
 { $r=Contract::profile($this->address,'ERC721')->call($this->client,'getReserve(uint256)',[$id],$block)[0];if(!in_array($r[2],['0','1','2'],true))throw new \RuntimeException('Unsupported reserve type');return ['token'=>$r[0],'amount'=>$r[1],'reserveType'=>$r[2]]; }
 public function checkReserveToken(string $token,string $block='latest'): bool
 { Contract::profile($this->center,'TestnetNFTCenter20261029')->call($this->client,'checkToken(address)',[$token],$block);return true; }
 public function mint(string $sender,string $recipient,string $uri,?string $id=null,string $amount='1',string $valueWei='0',string $reserveAmount='0',string $reserveToken='0x0000000000000000000000000000000000000000',?array $permit=null,string $block='latest'): TransactionDraft
 {
  foreach([$amount,$valueWei,$reserveAmount] as $n)Units::uint($n);$reserveToken=TransactionDraft::address($reserveToken);$zero='0x'.str_repeat('0',40);
  self::requireText($uri);
  if($amount==='0'||($this->standard===721&&($amount!=='1'||$id!==null))||($this->standard===1155&&$id===null))throw new \InvalidArgumentException('Invalid NFT mint request');
  if($valueWei!=='0'&&($reserveAmount!=='0'||$reserveToken!==$zero))throw new \InvalidArgumentException('Choose native or token reserve');
  $permitArgs=$permit===null?null:self::permit($permit);if($permit!==null&&($valueWei!=='0'||$reserveAmount==='0'||$reserveToken===$zero))throw new \InvalidArgumentException('Mint permit requires positive ERC20 reserve');
  $state=$this->collectionState($block);if(!$state['active']||$state['standard']!==$this->standard)throw new \RuntimeException('Inactive or mismatched NFT collection');$useDel=$valueWei!=='0';
  if(!$state['withReserve']){if($useDel||$reserveAmount!=='0'||$reserveToken!==$zero||$permit!==null)throw new \InvalidArgumentException('Reserveless collection cannot accept reserve');}
  else{
   $existing=$this->standard===1155?$this->reserve($id,$block):null;$total=$useDel?$valueWei:$reserveAmount;
   if($existing!==null&&$existing['reserveType']==='1'&&!$useDel&&$reserveAmount==='0'&&$reserveToken===$zero)$useDel=true;
   if(!$useDel){if($reserveAmount==='0'&&!($existing!==null&&$existing['reserveType']==='2'))throw new \InvalidArgumentException('Positive token reserve required');$this->checkReserveToken($reserveToken,$block);}
   if($existing!==null&&$existing['reserveType']!=='0'){
    if($existing['reserveType']!==($useDel?'1':'2')||$existing['token']!==$reserveToken)throw new \InvalidArgumentException('Existing ID reserve type/token cannot change');
    $rate=Contract::profile($this->address,'ERC1155')->call($this->client,'rate(uint256)',[$id],$block)[0];if($total!==bcmul($rate,$amount,0))throw new \InvalidArgumentException('Total reserve must equal rate times amount');
   }else{$minimum=Contract::profile($this->address,'ERC721')->call($this->client,'MIN_RESERVE()',[],$block)[0];if($minimum==='0'||bccomp(bcdiv($total,$amount,0),$minimum,0)<0)throw new \InvalidArgumentException('Reserve below NFT minimum');}
  }
  if($permitArgs!==null){$types=$this->standard===721?['address','string','uint256','address']:['address','uint256','uint256','string','uint256','address'];$types=[...$types,'uint256','uint8','bytes32','bytes32'];$args=$this->standard===721?[$recipient,$uri,$reserveAmount,$reserveToken]:[$recipient,$id,$amount,$uri,$reserveAmount,$reserveToken];$c=new Contract($this->address,[['type'=>'function','name'=>'mintByPermit','stateMutability'=>'nonpayable','inputs'=>array_map(fn($t)=>['name'=>'','type'=>$t],$types),'outputs'=>[]]]);return $c->draft($sender,'mintByPermit('.implode(',',$types).')',[...$args,...$permitArgs]);}
  if($useDel&&$valueWei==='0'){ $sig=$this->standard===721?'mintByDEL(address,string)':'mintByDEL(address,uint256,uint256,string)';$types=$this->standard===721?['address','string']:['address','uint256','uint256','string'];$args=$this->standard===721?[$recipient,$uri]:[$recipient,$id,$amount,$uri];$c=new Contract($this->address,[['type'=>'function','name'=>'mintByDEL','stateMutability'=>'payable','inputs'=>array_map(fn($t)=>['name'=>'','type'=>$t],$types),'outputs'=>[]]]);return $c->draft($sender,$sig,$args); }
  return $this->raw->mint($sender,$recipient,$uri,$id,$amount,$valueWei,$reserveAmount,$reserveToken);
 }
 public function mintPermitChecked(string $sender,string $recipient,string $uri,string $reserveAmount,string $reserveToken,string $deadline,string $signature,?string $id=null,string $amount='1',string $block='latest'): TransactionDraft
 { $data=(new PermitService($this->client,$reserveToken))->prepare($sender,$this->address,$reserveAmount,$deadline);$p=PermitService::verify($data,$signature);return $this->mint($sender,$recipient,$uri,$id,$amount,'0',$reserveAmount,$reserveToken,['deadline'=>$deadline,'v'=>$p['v'],'r'=>$p['r'],'s'=>$p['s']],$block); }
 public function addTokenReserve(string $sender,string $id,string $amount,?array $permit=null): TransactionDraft
 { Units::uint($amount);if($amount==='0')throw new \InvalidArgumentException('Positive reserve required');$args=[$id,$amount];$types=['uint256','uint256'];$name='addReserve';if($permit!==null){$name='addReserveByPermit';$args=[...$args,...self::permit($permit)];$types=[...$types,'uint256','uint8','bytes32','bytes32'];} $c=new Contract($this->address,[['type'=>'function','name'=>$name,'stateMutability'=>'nonpayable','inputs'=>array_map(fn($t)=>['name'=>'','type'=>$t],$types),'outputs'=>[]]]);return $c->draft($sender,$name.'('.implode(',',$types).')',$args); }
 public function delegatePermit(string $sender,string $delegation,string $validator,string $id,array $permit,string $amount='1',bool $autoApprove=false,string $profile='Delegation20261030',string $block='latest'): TransactionDraft
 { $args=self::permit($permit);if($this->standard!==721||$amount!=='1'||$autoApprove||$profile!=='Delegation20261030')throw new \InvalidArgumentException('Permit delegation requires one unified ERC721 without approval steps');$owner=(new NftReader($this->client,$this->address,721))->ownerOf($id,$block);if(TransactionDraft::address($owner)!==TransactionDraft::address($sender))throw new \RuntimeException('NFT owner mismatch');return Contract::profile($delegation,$profile)->draft($sender,'delegateNFTByPermit(address,address,uint256,uint256,uint8,bytes32,bytes32)',[$validator,$this->address,$id,...$args]); }
 public function delegatePermitChecked(string $sender,string $delegation,string $validator,string $id,string $deadline,string $signature,string $version='1',string $block='latest'): TransactionDraft
 { $verified=(new NftPermitService($this->client,$this->address,$this->standard,$version))->verifyExternal($sender,$delegation,$deadline,$signature,$id,$block);return $this->delegatePermit($sender,$delegation,$validator,$id,['deadline'=>$deadline,'v'=>$verified['v'],'r'=>$verified['r'],'s'=>$verified['s']],'1',false,'Delegation20261030',$block); }
 public function withdraw(string $sender,string $delegation,string $validator,string $id,string $amount='1',?string $holdTimestamp=null,string $profile='PythonDelegationNft',string $block='latest'): TransactionDraft
 { $this->checkPosition($sender,$delegation,$validator,$id,$amount,$holdTimestamp,$profile,$block);return $this->raw->withdraw($sender,$delegation,$validator,$id,$amount,$holdTimestamp,$profile); }
 public function transferStake(string $sender,string $delegation,string $validator,string $id,string $newValidator,string $amount='1',?string $holdTimestamp=null,string $profile='PythonDelegationNft',string $block='latest'): TransactionDraft
 { $this->checkPosition($sender,$delegation,$validator,$id,$amount,$holdTimestamp,$profile,$block);return $this->raw->transferStake($sender,$delegation,$validator,$id,$newValidator,$amount,$holdTimestamp,$profile); }
 private function checkPosition(string $sender,string $delegation,string $validator,string $id,string $amount,?string $holdTimestamp,string $profile,string $block): void
 { if($profile!=='Delegation20261030')return;if($holdTimestamp!==null&&$holdTimestamp==='0')throw new \InvalidArgumentException('Positive held-position timestamp required');$state=$this->collectionState($block);if($state['standard']!==$this->standard)throw new \RuntimeException('NFT collection standard mismatch');$position=(new NftStakingReader($this->client,$delegation))->stake($this->address,$id,$sender,$validator,$holdTimestamp??'0',$block);if(!$position['isActive']||$position['amount']==='0'||$position['amount']!==$amount)throw new \RuntimeException('Unified NFT exit/transfer moves the entire current position'); }
 public function setTokenUri(string $sender,string $id,string $uri,bool $allowLegacy=false): TransactionDraft
 { if(!$allowLegacy)throw new \InvalidArgumentException('Current NFT URI setter requires explicit legacy opt-in');return $this->raw->setTokenUri($sender,$id,$uri); }
}
