<?php
declare(strict_types=1);
namespace Decimal;

/** Explicit deployment factories. Current request profiles are the default; legacy routes stay separate. */
final readonly class Sdk
{
    public function __construct(public Client $client) {}
    public function tx(): TransactionExecutor { return new TransactionExecutor($this->client); }
    public function erc20(string $token): Erc20Service { return new Erc20Service($this->client,$token); }
    public function tokenEconomy(string $center): CurrentTokenEconomy { return new CurrentTokenEconomy($this->client,$center); }
    public function legacyTokenEconomy(string $center): TokenEconomy { return new TokenEconomy($this->client,$center); }
    public function tokenReader(string $token,bool $reserveless=false): TokenReader { return new TokenReader($this->client,$token,$reserveless); }
    public function nft(string $collection,int $standard,string $center): NftCurrentOperations { return new NftCurrentOperations($this->client,$collection,$standard,$center); }
    public function rawNft(string $collection,int $standard): NftOperations { return new NftOperations($this->client,$collection,$standard); }
    public function nftReader(string $collection,int $standard): NftReader { return new NftReader($this->client,$collection,$standard); }
    public function unifiedNftStaking(string $deployment): NftStakingReader { return new NftStakingReader($this->client,$deployment); }
    public function legacyNftStaking(string $deployment): NftLegacyStaking { return new NftLegacyStaking($this->client,$deployment); }
    public function permit(string $token): PermitService { return new PermitService($this->client,$token); }
    public function nftPermit(string $collection,int $standard,string $version): NftPermitService { return new NftPermitService($this->client,$collection,$standard,$version); }
    public function staking(string $deployment,string $wdel,string $masterValidator,string $profile='Delegation'): StakingService { return new StakingService($this->client,$deployment,$wdel,$masterValidator,$profile); }
    public function stakingReader(string $deployment,string $profile='Delegation'): StakingReader { return new StakingReader($this->client,Contract::profile($deployment,$profile)); }
    public function validators(string $deployment): ValidatorService { return new ValidatorService($this->client,$deployment); }
    public function bridge(string $deployment): BridgeService { return new BridgeService($this->client,$deployment); }
    public function checks(string $deployment): ChecksService { return new ChecksService($this->client,$deployment); }
}
