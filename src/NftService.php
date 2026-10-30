<?php
declare(strict_types=1);
namespace Decimal;
/** Checked ERC721/1155 transfers and explicit approval plans for Decimal NFT staking. */
final class NftService
{
    public function __construct(private readonly Client $client,public readonly string $address) { TransactionDraft::address($address); }
    public function transfer721(string $sender,string $recipient,string $tokenId,string $block='latest'): TransactionDraft
    {
        $token=Contract::profile($this->address,'ERC721');$owner=$token->call($this->client,'ownerOf(uint256)',[$tokenId],$block)[0];
        if(TransactionDraft::address($sender)!==TransactionDraft::address($owner))throw new \RuntimeException('Sender does not own NFT');
        return $token->draft($sender,'safeTransferFrom(address,address,uint256)',[$sender,$recipient,$tokenId]);
    }
    /** URI setters are available only in the explicitly selected Python source profiles. */
    public function setTokenUri(string $sender,string $tokenId,string $uri,string $profile='PythonErc721'): TransactionDraft
    {
        if(!in_array($profile,['PythonErc721','PythonErc1155'],true))throw new \InvalidArgumentException('URI setter requires an explicit source ABI with setTokenURI');
        $draft=Contract::profile($this->address,$profile)->draft($sender,'setTokenURI(uint256,string)',[$tokenId,$uri]);
        $this->client->simulate($draft);return $draft;
    }
    public function transfer1155(string $sender,string $recipient,string $tokenId,string $amount,string $data='0x',string $block='latest'): TransactionDraft
    {
        Units::uint($amount);if($amount==='0')throw new \InvalidArgumentException('Positive NFT amount required');$token=Contract::profile($this->address,'ERC1155');
        $balance=$token->call($this->client,'balanceOf(address,uint256)',[$sender,$tokenId],$block)[0];if(bccomp($balance,$amount,0)<0)throw new \RuntimeException('Insufficient NFT balance');
        return $token->draft($sender,'safeTransferFrom(address,address,uint256,uint256,bytes)',[$sender,$recipient,$tokenId,$amount,$data]);
    }
    public function delegate721(string $sender,string $delegation,string $validator,string $tokenId,string $block='latest',string $profile='DelegationNFT'): array
    {
        $this->delegationProfile($profile);
        $token=Contract::profile($this->address,'ERC721');$owner=$token->call($this->client,'ownerOf(uint256)',[$tokenId],$block)[0];
        if(TransactionDraft::address($owner)!==TransactionDraft::address($sender))throw new \RuntimeException('Sender does not own NFT');
        $approved=$token->call($this->client,'getApproved(uint256)',[$tokenId],$block)[0];$steps=[];
        if(TransactionDraft::address($approved)!==TransactionDraft::address($delegation)&&!$token->call($this->client,'isApprovedForAll(address,address)',[$sender,$delegation],$block)[0])$steps[]=$token->draft($sender,'approve(address,uint256)',[$delegation,$tokenId]);
        $action=Contract::profile($delegation,$profile)->draft($sender,$profile==='Delegation20261030'?'delegateNFT(address,address,uint256)':'delegateDRC721(address,address,uint256)',[$validator,$this->address,$tokenId]);
        return ['approvals'=>$steps,'action'=>$action];
    }
    public function delegate1155(string $sender,string $delegation,string $validator,string $tokenId,string $amount,string $block='latest',string $profile='DelegationNFT'): array
    {
        $this->delegationProfile($profile);
        Units::uint($amount);if($amount==='0')throw new \InvalidArgumentException('Positive NFT amount required');$token=Contract::profile($this->address,'ERC1155');
        if(bccomp($token->call($this->client,'balanceOf(address,uint256)',[$sender,$tokenId],$block)[0],$amount,0)<0)throw new \RuntimeException('Insufficient NFT balance');
        $steps=[];if(!$token->call($this->client,'isApprovedForAll(address,address)',[$sender,$delegation],$block)[0])$steps[]=$token->draft($sender,'setApprovalForAll(address,bool)',[$delegation,true]);
        return ['approvals'=>$steps,'action'=>Contract::profile($delegation,$profile)->draft($sender,$profile==='Delegation20261030'?'delegateNFT1155(address,address,uint256,uint256)':'delegateDRC1155(address,address,uint256,uint256)',[$validator,$this->address,$tokenId,$amount])];
    }
    private function delegationProfile(string $profile): void
    {
        if(!in_array($profile,['DelegationNFT','Delegation20261030'],true))throw new \InvalidArgumentException('Explicit NFT delegation profile required');
        if($this->client->network->chainId===75 && $profile==='DelegationNFT')throw new \RuntimeException('Legacy NFT deposits are inactive on upgraded mainnet; explicitly select the unified Delegation20261030 deployment');
    }
}
