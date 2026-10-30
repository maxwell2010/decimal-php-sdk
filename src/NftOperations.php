<?php
declare(strict_types=1);
namespace Decimal;
/** Explicit NFT request semantics. Amounts and IDs are raw decimal strings. */
final class NftOperations
{
    public function __construct(private readonly Client $client, public readonly string $address, public readonly int $standard)
    { TransactionDraft::address($address); if(!in_array($standard,[721,1155],true))throw new \InvalidArgumentException('NFT standard must be 721 or 1155'); }
    public static function createReservelessCollection(string $center,int $standard,string $sender,string $creator,string $symbol,string $name,string $uri,bool $refundable,bool $burnable): TransactionDraft
    { if(!in_array($standard,[721,1155],true))throw new \InvalidArgumentException('Invalid NFT standard');return Contract::profile($center,'TestnetNFTCenter20261029')->draft($sender,'createDRC'.$standard.'Reserveless((address,string,string,string,bool,bool))',[[$creator,$symbol,$name,$uri,$refundable,$burnable]]); }
    public function addReserve(string $sender,string $id,string $amount): TransactionDraft
    { Units::uint($amount);if($amount==='0')throw new \InvalidArgumentException('Positive reserve required');return Contract::profile($this->address,'ERC'.$this->standard)->draft($sender,'addReserve(uint256,uint256)',[$id,$amount]); }
    public function addEthReserve(string $sender,string $id,string $valueWei): TransactionDraft
    { Units::uint($valueWei);if($valueWei==='0')throw new \InvalidArgumentException('Positive reserve required');return Contract::profile($this->address,'ERC'.$this->standard)->draft($sender,'addReserveByETH(uint256)',[$id],$valueWei); }
    /** Executor may differ from owner; node enforces current operator/token approval. */
    public function transferFrom(string $executor,string $owner,string $recipient,string $id,string $amount='1',?string $data=null,bool $safe=true): TransactionDraft
    {
        $c=new Contract($this->address,json_decode(file_get_contents(dirname(__DIR__).'/resources/nft-erc'.$this->standard.'-csharp.json'),true,512,JSON_THROW_ON_ERROR));
        if($this->standard===1155){if(!$safe)throw new \InvalidArgumentException('ERC1155 requires safe transfer');return $c->draft($executor,'safeTransferFrom(address,address,uint256,uint256,bytes)',[$owner,$recipient,$id,$amount,$data??'0x']);}
        if($amount!=='1')throw new \InvalidArgumentException('ERC721 amount must be one');
        $sig=$safe?($data===null?'safeTransferFrom(address,address,uint256)':'safeTransferFrom(address,address,uint256,bytes)'):'transferFrom(address,address,uint256)';$args=[$owner,$recipient,$id];if($safe&&$data!==null)$args[]=$data;return $c->draft($executor,$sig,$args);
    }
    private function contract(): Contract { return Contract::profile($this->address,$this->standard===721?'PythonErc721':'PythonErc1155'); }
    private function ownership(string $sender,string $id,string $amount,string $block): void
    {
        Units::uint($id);Units::uint($amount);if($amount==='0'||($this->standard===721&&$amount!=='1'))throw new \InvalidArgumentException('Invalid NFT amount');
        $c=$this->contract();
        if($this->standard===721){if(TransactionDraft::address($c->call($this->client,'ownerOf(uint256)',[$id],$block)[0])!==TransactionDraft::address($sender))throw new \RuntimeException('NFT owner mismatch');}
        elseif(bccomp($c->call($this->client,'balanceOf(address,uint256)',[$sender,$id],$block)[0],$amount,0)<0)throw new \RuntimeException('Insufficient NFT balance');
    }
    public function approve(string $sender,string $operator,string $id,string $block='latest'): TransactionDraft
    { if($this->standard!==721)throw new \InvalidArgumentException('ERC1155 uses operator approval');$this->ownership($sender,$id,'1',$block);return $this->contract()->draft($sender,'approve(address,uint256)',[$operator,$id]); }
    public function setApprovalForAll(string $sender,string $operator,bool $approved): TransactionDraft
    { return $this->contract()->draft($sender,'setApprovalForAll(address,bool)',[$operator,$approved]); }
    public function burn(string $sender,string $id,string $amount='1',string $block='latest'): TransactionDraft
    { $this->ownership($sender,$id,$amount,$block);return $this->contract()->draft($sender,$this->standard===721?'burn(uint256)':'burn(uint256,uint256)',$this->standard===721?[$id]:[$id,$amount]); }
    public function disableMint(string $sender): TransactionDraft { return $this->contract()->draft($sender,'disableMint()',[]); }
    public function setTokenUri(string $sender,string $id,string $uri): TransactionDraft { return $this->contract()->draft($sender,'setTokenURI(uint256,string)',[$id,$uri]); }
    public function addDelReserve(string $sender,string $id,string $reserveWei): TransactionDraft
    { Units::uint($reserveWei);return $this->contract()->draft($sender,'addReserveByDEL(uint256)',[$id],$reserveWei); }
    public static function createCollection(string $center,int $standard,string $sender,string $creator,string $symbol,string $name,string $uri,bool $refundable): TransactionDraft
    { if(!in_array($standard,[721,1155],true))throw new \InvalidArgumentException('Invalid NFT standard');return Contract::profile($center,'PythonNftCenter')->draft($sender,'createERC'.$standard.'((address,string,string,string,bool))',[[$creator,$symbol,$name,$uri,$refundable]]); }
    /** Native total value and ERC20 reserve are mutually exclusive, as in Python. */
    public function mint(string $sender,string $recipient,string $uri,?string $id=null,string $amount='1',string $valueWei='0',string $reserveAmount='0',string $reserveToken='0x0000000000000000000000000000000000000000',string $nativeMethod='mintByDEL'): TransactionDraft
    {
        foreach([$amount,$valueWei,$reserveAmount] as $n)Units::uint($n);TransactionDraft::address($reserveToken);
        if($amount==='0'||($this->standard===721&&$amount!=='1'))throw new \InvalidArgumentException('Invalid NFT amount');
        if(!in_array($nativeMethod,['mintByDEL','mintByETH'],true))throw new \InvalidArgumentException('Invalid native mint method');
        if($valueWei!=='0'&&($reserveAmount!=='0'||$reserveToken!=='0x'.str_repeat('0',40)))throw new \InvalidArgumentException('Choose native or token reserve');
        if($this->standard===1155&&$id===null)throw new \InvalidArgumentException('ERC1155 ID required');
        $native=$valueWei!=='0';$c=$this->contract();
        if($native&&$nativeMethod==='mintByETH'){
            $abi=json_decode(file_get_contents(dirname(__DIR__).'/abi/'.($this->standard===721?'PythonErc721':'PythonErc1155').'.json'),true,512,JSON_THROW_ON_ERROR);
            foreach($abi as &$entry)if(($entry['name']??'')==='mintByDEL')$entry['name']='mintByETH';unset($entry);$c=new Contract($this->address,$abi);
        }
        $sig=$native?$nativeMethod.($this->standard===721?'(address,string)':'(address,uint256,uint256,string)'):($this->standard===721?'mint(address,string,uint256,address)':'mint(address,uint256,uint256,string,uint256,address)');
        $args=$this->standard===721?[$recipient,$uri]:[$recipient,$id,$amount,$uri];if(!$native)$args=[...$args,$reserveAmount,$reserveToken];return $c->draft($sender,$sig,$args,$valueWei);
    }
    public function transferBatch1155(string $sender,string $recipient,array $ids,array $amounts,string $data='0x',string $block='latest'): TransactionDraft
    {
        if($this->standard!==1155||!array_is_list($ids)||!array_is_list($amounts)||!$ids||count($ids)!==count($amounts))throw new \InvalidArgumentException('Matching nonempty NFT arrays required');
        $totals=[];foreach($ids as $i=>$id){Units::uint($id);Units::uint($amounts[$i]);if($amounts[$i]==='0')throw new \InvalidArgumentException('Positive amount required');$key='id:'.$id;$totals[$key]=bcadd($totals[$key]??'0',$amounts[$i],0);}
        foreach($totals as $key=>$amount)$this->ownership($sender,substr($key,3),$amount,$block);
        return $this->contract()->draft($sender,'safeBatchTransferFrom(address,address,uint256[],uint256[],bytes)',[$sender,$recipient,$ids,$amounts,$data]);
    }
    /** Plan approvals explicitly; execution must confirm approvals before preparing the action. */
    public function hold(string $sender,string $delegation,string $validator,string $id,string $holdTimestamp,string $amount='1',bool $autoApprove=true,string $profile='Delegation20261030',string $block='latest'): array
    {
        return $this->deposit($sender,$delegation,$validator,$id,$holdTimestamp,$amount,$autoApprove,$profile,$block);
    }
    public function delegate(string $sender,string $delegation,string $validator,string $id,string $amount='1',bool $autoApprove=true,string $profile='Delegation20261030',string $block='latest'): array
    { return $this->deposit($sender,$delegation,$validator,$id,null,$amount,$autoApprove,$profile,$block); }
    private function deposit(string $sender,string $delegation,string $validator,string $id,?string $holdTimestamp,string $amount,bool $autoApprove,string $profile,string $block): array
    {
        if(!in_array($profile,['Delegation20261030','PythonDelegationNft'],true)||($this->client->network->chainId===75&&$profile==='PythonDelegationNft'))throw new \InvalidArgumentException('Explicit active NFT deposit profile required');
        $this->ownership($sender,$id,$amount,$block);$approvals=[];
        if(!$this->contract()->call($this->client,'isApprovedForAll(address,address)',[$sender,$delegation],$block)[0]){if(!$autoApprove)throw new \RuntimeException('NFT approval required');$approvals[]=$this->setApprovalForAll($sender,$delegation,true);}
        $method=$profile==='Delegation20261030'?($this->standard===721?'delegateNFTHold':'delegateNFT1155Hold'):($this->standard===721?'delegateHoldDRC721':'delegateHoldDRC1155');
        if($holdTimestamp===null)$method=$profile==='Delegation20261030'?($this->standard===721?'delegateNFT':'delegateNFT1155'):($this->standard===721?'delegateDRC721':'delegateDRC1155');
        $args=[$validator,$this->address,$id];if($this->standard===1155)$args[]=$amount;if($holdTimestamp!==null)$args[]=$holdTimestamp;
        $types=['address','address','uint256'];if($this->standard===1155)$types[]='uint256';if($holdTimestamp!==null)$types[]='uint256';
        return ['approvals'=>$approvals,'action'=>Contract::profile($delegation,$profile)->draft($sender,$method.'('.implode(',',$types).')',$args)];
    }
    /** Exits always use the caller-selected original position deployment/profile. */
    public function withdraw(string $sender,string $delegation,string $validator,string $id,string $amount='1',?string $holdTimestamp=null,string $profile='PythonDelegationNft'): TransactionDraft
    { return $this->stakeAction(false,$sender,$delegation,$validator,$id,$amount,$holdTimestamp,null,$profile); }
    public function transferStake(string $sender,string $delegation,string $validator,string $id,string $newValidator,string $amount='1',?string $holdTimestamp=null,string $profile='PythonDelegationNft'): TransactionDraft
    { return $this->stakeAction(true,$sender,$delegation,$validator,$id,$amount,$holdTimestamp,$newValidator,$profile); }
    private function stakeAction(bool $transfer,string $sender,string $delegation,string $validator,string $id,string $amount,?string $holdTimestamp,?string $newValidator,string $profile): TransactionDraft
    {
        if(!in_array($profile,['PythonDelegationNft','DelegationNFT','Delegation20261030'],true))throw new \InvalidArgumentException('Explicit NFT position profile required');
        Units::uint($amount);if($amount==='0'||($this->standard===721&&$amount!=='1'))throw new \InvalidArgumentException('Invalid position amount');
        $unified=$profile==='Delegation20261030';$method=$transfer?'transfer':'withdraw';if($unified)$method.=$this->standard===721?'NFT':'NFT1155';if($holdTimestamp!==null)$method.='Hold';
        $args=[$validator,$this->address,$id];$types=['address','address','uint256'];if(!$unified){$args[]=$amount;$types[]='uint256';}if($holdTimestamp!==null){$args[]=$holdTimestamp;$types[]='uint256';}if($transfer){$args[]=$newValidator;$types[]='address';}
        return Contract::profile($delegation,$profile)->draft($sender,$method.'('.implode(',',$types).')',$args);
    }
    /** Preserve positional ID/amount pairing and deployment for each exit. */
    public function withdrawMany(string $sender,string $delegation,string $validator,array $ids,array $amounts,?string $holdTimestamp=null,string $profile='PythonDelegationNft'): array
    { if(!array_is_list($ids)||!array_is_list($amounts)||!$ids||count($ids)!==count($amounts))throw new \InvalidArgumentException('Matching NFT position arrays required');$out=[];foreach($ids as $i=>$id)$out[]=$this->withdraw($sender,$delegation,$validator,$id,$amounts[$i],$holdTimestamp,$profile);return $out; }
}
