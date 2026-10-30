<?php
declare(strict_types=1);
namespace Decimal;
/** Source-backed reserve and reserveless views, with explicit pinned snapshots and quote overloads. */
final class TokenReader
{
    private Contract $contract;
    public function __construct(private readonly Client $client,public readonly string $address,private readonly bool $reserveless=false) { $this->contract=Contract::profile($address,$reserveless?'TestnetReservelessToken20261029':'Token'); }
    public function read(string $field,string $block='latest'): mixed { $allowed=$this->reserveless?['cap','mintable','burnable','owner','identity']:['creator','identity','reserve','crr','minTotalSupply','maxTotalSupply','getCurrentPrice'];if(!in_array($field,$allowed,true))throw new \InvalidArgumentException('Unsupported token field');return $this->contract->call($this->client,$field.'()',[],$block)[0]; }
    public function state(string $block='latest'): array { if($block==='latest'){$this->client->verifyChain();$block=$this->client->rpc->call('eth_blockNumber');Units::decimal($block);} $result=['block'=>$block,'metadata'=>(new Erc20Service($this->client,$this->address))->info($block)];foreach($this->reserveless?['cap','mintable','burnable','owner','identity']:['creator','identity','reserve','crr','minTotalSupply','maxTotalSupply','getCurrentPrice'] as $field)$result[$field]=$this->read($field,$block);return $result; }
    public function quote(string $kind,string $amountRaw,?array $customState=null,string $block='latest'): string { if($this->reserveless||!in_array($kind,['calculateBuyInput','calculateBuyOutput','calculateSellInput','calculateSellOutput'],true))throw new \InvalidArgumentException('Unsupported reserve quote');Units::uint($amountRaw);if($amountRaw==='0')throw new \InvalidArgumentException('Positive quote amount required');$args=[$amountRaw];$types='uint256';if($customState!==null){if(!array_is_list($customState)||count($customState)!==3)throw new \InvalidArgumentException('Custom supply/reserve/crr tuple required');foreach($customState as $value)Units::uint($value);$args=[...$customState,$amountRaw];$types='uint256,uint256,uint256,uint256';}return $this->contract->call($this->client,$kind.'('.$types.')',$args,$block)[0]; }
}
