<?php
declare(strict_types=1);
namespace Decimal;
/** Explicit deployed native-reserve interface observed on Decimal testnet. */
final class DeployedNft
{
    private Contract $contract;
    public function __construct(private readonly Client $client,public readonly string $address,public readonly int $standard)
    {
        if(!in_array($standard,[721,1155],true))throw new \InvalidArgumentException('Unsupported NFT standard');
        $mint=$standard===721?[['name'=>'recipient','type'=>'address'],['name'=>'tokenURI','type'=>'string']]:[['name'=>'recipient','type'=>'address'],['name'=>'tokenId','type'=>'uint256'],['name'=>'amountToMint','type'=>'uint256'],['name'=>'tokenURI','type'=>'string']];
        $this->contract=new Contract($address,[
            ['type'=>'function','name'=>'mintByDEL','stateMutability'=>'payable','inputs'=>$mint,'outputs'=>$standard===721?[['type'=>'uint256','name'=>'tokenId']]:[]],
            ['type'=>'function','name'=>'addReserveByDEL','stateMutability'=>'payable','inputs'=>[['type'=>'uint256','name'=>'tokenId']],'outputs'=>[]],
            ['type'=>'function','name'=>'getReserve','stateMutability'=>'view','inputs'=>[['type'=>'uint256','name'=>'tokenId']],'outputs'=>[['type'=>'tuple','name'=>'reserve','components'=>[['type'=>'address','name'=>'token'],['type'=>'uint256','name'=>'amount'],['type'=>'uint8','name'=>'reserveType']]]]],
        ]);
    }
    private static function positive(string $n): void { Units::uint($n);if($n==='0')throw new \InvalidArgumentException('Positive value required'); }
    /** Value is total native reserve, never per-unit and never gas. */
    public function mint(string $sender,string $recipient,string $uri,string $totalReserve,?string $tokenId=null,string $quantity='1'): TransactionDraft
    {
        self::positive($totalReserve);if($uri==='')throw new \InvalidArgumentException('NFT URI required');
        if($this->standard===721){if($tokenId!==null||$quantity!=='1')throw new \InvalidArgumentException('721 ID is allocated by mint');return $this->contract->draft($sender,'mintByDEL(address,string)',[$recipient,$uri],$totalReserve);}
        if($tokenId===null)throw new \InvalidArgumentException('1155 token ID required');self::positive($quantity);
        return $this->contract->draft($sender,'mintByDEL(address,uint256,uint256,string)',[$recipient,$tokenId,$quantity,$uri],$totalReserve);
    }
    public function reserve(string $tokenId,string $block='latest'): array
    {
        $r=$this->contract->call($this->client,'getReserve(uint256)',[$tokenId],$block)[0];return ['token'=>$r[0],'amount'=>$r[1],'reserveType'=>$r[2]];
    }
    public function addDelReserve(string $sender,string $tokenId,string $amount,string $expectedDelReserveType,string $block='latest'): TransactionDraft
    {
        self::positive($amount);Units::uint($expectedDelReserveType);$reserve=$this->reserve($tokenId,$block);
        // Caller supplies the reviewed deployment enum value; no enum value is guessed.
        if($reserve['token']!=='0x'.str_repeat('0',40)||$reserve['reserveType']!==$expectedDelReserveType)throw new \RuntimeException('NFT does not have a native DEL reserve');
        return $this->contract->draft($sender,'addReserveByDEL(uint256)',[$tokenId],$amount);
    }
    public static function createCollection(string $center,int $standard,string $sender,string $creator,string $symbol,string $name,string $uri,bool $refundable,bool $burnable): TransactionDraft
    {
        if(!in_array($standard,[721,1155],true)||$symbol===''||$name==='')throw new \InvalidArgumentException('Invalid collection metadata');
        return Contract::profile($center,'TestnetNFTCenter20261029')->draft($sender,'createDRC'.$standard.'((address,string,string,string,bool,bool))',[[$creator,$symbol,$name,$uri,$refundable,$burnable]]);
    }
    public static function createdCollection(array $receipt,string $center,string $creator,string $symbol): array
    {
        $matches=[];
        foreach(Events::receipt('TestnetNFTCenter20261029',$receipt,$center) as $e)if($e['event']==='NFTCreated'){
            $a=$e['args'];$nft=$a['nft'];if(TransactionDraft::address($nft[0])===TransactionDraft::address($creator)&&$nft[1]===$symbol){
                $address=TransactionDraft::address($a['tokenAddress']);if($address==='0x'.str_repeat('0',40))throw new \RuntimeException('Zero collection address');
                $matches[]=['address'=>$address,'nftType'=>$a['nftType'],'creator'=>$nft[0],'symbol'=>$nft[1],'name'=>$nft[2],'contractURI'=>$nft[3],'refundable'=>$nft[4],'burnable'=>$nft[5]];
            }
        }
        if(count($matches)!==1)throw new \RuntimeException('Collection creation event absent or ambiguous');return $matches[0];
    }
}
