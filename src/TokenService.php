<?php
declare(strict_types=1);
namespace Decimal;
/** Reserveless-token preconditions use the separately observed reserveless ABI. */
final class TokenService
{
    private Contract $contract;
    public function __construct(private readonly Client $client,string $address)
    { $this->contract=Contract::profile($address,'TestnetReservelessToken20261029'); }
    public function mint(string $sender,string $recipient,string $amount,string $block='latest'): TransactionDraft
    {
        Units::uint($amount);if($amount==='0')throw new \InvalidArgumentException('Positive mint amount required');
        $cap=$this->contract->call($this->client,'cap()',[],$block)[0];$supply=$this->contract->call($this->client,'totalSupply()',[],$block)[0];
        if(bccomp(bcadd($supply,$amount,0),$cap,0)>0)throw new \RuntimeException('Mint exceeds token cap');
        $draft=$this->contract->draft($sender,'mint(address,uint256)',[$recipient,$amount]);
        $this->client->simulate($draft);return $draft;
    }
    public function burn(string $sender,string $amount,string $block='latest'): TransactionDraft
    {
        Units::uint($amount);if($amount==='0')throw new \InvalidArgumentException('Positive burn amount required');
        if(bccomp($this->contract->call($this->client,'balanceOf(address)',[$sender],$block)[0],$amount,0)<0)throw new \RuntimeException('Burn exceeds token balance');
        $draft=$this->contract->draft($sender,'burn(uint256)',[$amount]);$this->client->simulate($draft);return $draft;
    }
    /** Returns a simulated creation draft, without guessing or sending a native fee. */
    public static function createReserveless(Client $client,string $center,string $sender,string $name,string $symbol,bool $mintable,bool $burnable,string $initialMint,string $cap,string $identity): TransactionDraft
    {
        Units::uint($initialMint);Units::uint($cap);
        if($name===''||$symbol===''||$cap==='0'||bccomp($initialMint,$cap,0)>0)throw new \InvalidArgumentException('Invalid token metadata or supply');
        $draft=Contract::profile($center,'TestnetTokenCenter20261029')->draft($sender,'createTokenReserveless(string,string,bool,bool,uint256,uint256,string)',[$name,$symbol,$mintable,$burnable,$initialMint,$cap,$identity]);
        $client->simulate($draft);return $draft;
    }
}
