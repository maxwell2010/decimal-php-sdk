<?php
declare(strict_types=1);
namespace Decimal;
/** Caller-supplied query transport; the schema codec never accesses the network. */
final class CosmosRest
{
    public function __construct(private readonly Api $api) {}
    public function account(string $address): mixed { Address::fromBech32($address);return $this->api->get('cosmos/auth/v1beta1/accounts/'.$address); }
    public function balances(string $address,array $query=[]): mixed { Address::fromBech32($address);return $this->api->get('cosmos/bank/v1beta1/balances/'.$address,$query); }
    public function transaction(string $hash): mixed { if(!preg_match('/^[a-fA-F0-9]{64}$/D',$hash))throw new \InvalidArgumentException('Invalid Cosmos hash');return $this->api->get('cosmos/tx/v1beta1/txs/'.$hash); }
}
