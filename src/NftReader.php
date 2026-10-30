<?php
declare(strict_types=1);
namespace Decimal;
/** Explicit collection read ABI derived from the C# SDK, with pinned block support. */
final class NftReader
{
 private Contract $contract;
 public function __construct(private readonly Client $client,string $address,private readonly int $standard)
 { if(!in_array($standard,[721,1155],true))throw new \InvalidArgumentException('Invalid NFT standard');$this->contract=new Contract($address,json_decode(file_get_contents(dirname(__DIR__).'/resources/nft-erc'.$standard.'-csharp.json'),true,512,JSON_THROW_ON_ERROR)); }
 public function read(string $signature,array $args=[],string $block='latest'): mixed
 { return $this->contract->call($this->client,$signature,$args,$block)[0]; }
 public function name(string $block='latest'): string { return $this->read('name()',[],$block); }
 public function symbol(string $block='latest'): string { return $this->read('symbol()',[],$block); }
 public function contractUri(string $block='latest'): string { return $this->read('contractURI()',[],$block); }
 public function creator(string $block='latest'): string { return $this->read('creator()',[],$block); }
 public function allowMint(string $block='latest'): bool { return $this->read('getAllowMint()',[],$block); }
 public function refundable(string $block='latest'): bool { return $this->read('getRefundable()',[],$block); }
 public function nftType(bool $legacy=false,string $block='latest'): string { return $this->read($legacy?'getNftType()':'getNFTType()',[],$block); }
 public function ownerOf(string $id,string $block='latest'): string { return $this->read('ownerOf(uint256)',[$id],$block); }
 public function tokenUri(string $id,string $block='latest'): string { return $this->read($this->standard===721?'tokenURI(uint256)':'uri(uint256)',[$id],$block); }
 public function balanceOf(string $owner,?string $id=null,string $block='latest'): string
 { if($this->standard===1155&&$id===null)throw new \InvalidArgumentException('ERC1155 ID required');return $this->read($this->standard===721?'balanceOf(address)':'balanceOf(address,uint256)',$this->standard===721?[$owner]:[$owner,$id],$block); }
 public function balanceOfBatch(array $owners,array $ids,string $block='latest'): array
 { if(!array_is_list($owners)||!array_is_list($ids)||!$ids||count($owners)!==count($ids))throw new \InvalidArgumentException('Matching nonempty arrays required');return $this->read('balanceOfBatch(address[],uint256[])',[$owners,$ids],$block); }
 public function isApprovedForAll(string $owner,string $operator,string $block='latest'): bool { return $this->read('isApprovedForAll(address,address)',[$owner,$operator],$block); }
 public function getApproved(string $id,string $block='latest'): string { return $this->read('getApproved(uint256)',[$id],$block); }
 public function rate(string $id,string $block='latest'): string { return $this->read('rate(uint256)',[$id],$block); }
 public function exists(string $id,string $block='latest'): bool { return $this->read('exists(uint256)',[$id],$block); }
 public function totalSupply(?string $id=null,string $block='latest'): string { return $this->read($this->standard===721?'totalSupply()':'totalSupply(uint256)',$this->standard===721?[]:[$id],$block); }
}
