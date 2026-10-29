<?php
declare(strict_types=1);
namespace Decimal;
final class Deployment
{
    private const IMPLEMENTATION='0x360894a13ba1a3210667c828492db98dca3e2076cc3735a920a3ca505d382bbc';
    private const BEACON='0xa3f0ad74e5423aebfd80d3ef4346578335a9a72aeaee59ff6cb3582b35133d50';
    public static function verify(Client $client,string $address,string $expectedRuntimeHash,?string $expectedImplementation=null): void
    {
        TransactionDraft::address($address);$client->verifyChain();
        $code=$client->rpc->call('eth_getCode',[$address,'latest']);
        if(!preg_match('/^0x(?:[a-fA-F0-9]{2})+$/D',$code)||strtolower($expectedRuntimeHash)!=='0x'.\kornrunner\Keccak::hash(hex2bin(substr($code,2)),256))throw new \RuntimeException('Deployment runtime hash mismatch');
        if($expectedImplementation!==null){
            $implementation=self::implementation($client,$address);
            if($implementation===null||$implementation!==TransactionDraft::address($expectedImplementation))throw new \RuntimeException('Proxy implementation mismatch');
        }
    }
    public static function implementation(Client $client,string $proxy): ?string
    {
        TransactionDraft::address($proxy);$client->verifyChain();$codec=new AbiCodec();
        $word=$client->rpc->call('eth_getStorageAt',[$proxy,self::IMPLEMENTATION,'latest']);
        $impl=$codec->decodeParameters(['address'],$word)[0];if($impl!=='0x'.str_repeat('0',40))return $impl;
        $word=$client->rpc->call('eth_getStorageAt',[$proxy,self::BEACON,'latest']);$beacon=$codec->decodeParameters(['address'],$word)[0];
        if($beacon==='0x'.str_repeat('0',40))return null;
        return $codec->decodeParameters(['address'],$client->call(['to'=>$beacon,'data'=>$codec->encodeFunctionSignature('implementation()')]))[0];
    }
    public static function resolve(Client $client,string $name,string $profile='ContractCenter'): string
    {
        $contract=Contract::profile($client->network->contractCenter,$profile);$result=$contract->call($client,'getContractAddress(string)',[$name]);
        $address=TransactionDraft::address($result[0]);if($address==='0x'.str_repeat('0',40))throw new \RuntimeException('System contract unresolved');return $address;
    }
}
