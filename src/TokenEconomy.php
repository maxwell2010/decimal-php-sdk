<?php
declare(strict_types=1);
namespace Decimal;
/** Decimal reserve-token economics and explicit approval/permit plans. Amounts are human units unless marked Raw/Wei. */
final class TokenEconomy
{
    private Contract $center;
    public function __construct(private readonly Client $client,public readonly string $tokenCenter) { $this->center=Contract::profile($tokenCenter,'TokenCenter'); }
    public function buy(string $sender,string $token,string $amountDel,string $minOutRaw='0',?string $recipient=null): TransactionDraft { Units::uint($minOutRaw);return Contract::profile($token,'Token')->draft($sender,'buy(uint256,address)',[$minOutRaw,$recipient??$sender],Units::parse($amountDel)); }
    public function sell(string $sender,string $token,string $amount,string $minDelOutWei='1',?int $decimals=null,?string $recipient=null,string $block='latest'): TransactionDraft { $erc=new Erc20Service($this->client,$token);$raw=$erc->amount($amount,$decimals,$block);$erc->requireBalance($sender,$raw,$block);return Contract::profile($token,'Token')->draft($sender,'sell(uint256,uint256,address)',[$raw,$minDelOutWei,$recipient??$sender]); }
    public function convert(string $sender,string $tokenIn,string $tokenOut,string $amountIn,string $minOut,?int $inDecimals=null,?int $outDecimals=null,bool $autoApprove=true,bool $resetExisting=false,?Wallet $permitWallet=null,string $permitName='',string $permitVersion='1',string $deadline='115792089237316195423570985008687907853269984665640564039457584007913129639935',string $block='latest'): array
    {
        if(TransactionDraft::address($tokenIn)===TransactionDraft::address($tokenOut))throw new \InvalidArgumentException('Conversion tokens must differ');
        $erc=new Erc20Service($this->client,$tokenIn);$raw=$erc->amount($amountIn,$inDecimals,$block);$minimum=(new Erc20Service($this->client,$tokenOut))->amount($minOut,$outDecimals,$block);$erc->requireBalance($sender,$raw,$block);
        $args=[$tokenIn,$tokenOut,$raw,$minimum,$sender];$signature='convert(address,address,uint256,uint256,address)';$steps=[];
        $allowance=$erc->allowance($sender,$this->tokenCenter,$block);
        if(bccomp($allowance,$raw,0)<0){
            if($permitWallet!==null){
                Units::uint($deadline);if(bccomp($deadline,(string)time(),0)<=0)throw new \InvalidArgumentException('Permit deadline must be in the future');
                if(TransactionDraft::address($sender)!==TransactionDraft::address($permitWallet->address))throw new \InvalidArgumentException('Permit signer differs from sender');
                if($permitName==='')$permitName=$erc->info($block)['name'];
                $p=TypedData::checkedPermit($this->client,$permitWallet,$tokenIn,$permitName,$permitVersion,$this->tokenCenter,$raw,$deadline);
                $signature='convert(address,address,uint256,uint256,address,uint256,uint8,bytes32,bytes32)';array_push($args,$deadline,$p['v'],$p['r'],$p['s']);
            }else{
                if(!$autoApprove)throw new \RuntimeException('Insufficient ERC20 allowance and automatic approval disabled');
                if($resetExisting&&$allowance!=='0')$steps[]=$erc->approve($sender,$this->tokenCenter,'0',0,$block);
                $steps[]=$erc->approve($sender,$this->tokenCenter,$raw,0,$block);
            }
        }
        return ['approvals'=>$steps,'action'=>$this->center->draft($sender,$signature,$args)];
    }
    public function mint(string $sender,string $token,string $recipient,string $amount,?int $decimals=null,string $block='latest'): TransactionDraft { $raw=(new Erc20Service($this->client,$token))->amount($amount,$decimals,$block);return (new TokenService($this->client,$token))->mint($sender,$recipient,$raw,$block); }
    public function burn(string $sender,string $token,string $amount,?int $decimals=null,string $block='latest'): TransactionDraft { $raw=(new Erc20Service($this->client,$token))->amount($amount,$decimals,$block);return (new TokenService($this->client,$token))->burn($sender,$raw,$block); }
    public function createReserveless(string $sender,string $name,string $symbol,bool $mintable,bool $burnable,string $initialMintRaw,string $capRaw,string $identity): TransactionDraft { return TokenService::createReserveless($this->client,$this->tokenCenter,$sender,$name,$symbol,$mintable,$burnable,$initialMintRaw,$capRaw,$identity); }
    public function tradeRaw(string $kind,string $sender,string $token,string $amountRaw,string $limitRaw,string $recipient): TransactionDraft
    {
        $spec=match($kind){'buyTokenForExactDEL'=>['buyTokenForExactDEL(uint256,address)',[$limitRaw,$recipient],$amountRaw],'buyExactTokenForDEL'=>['buyExactTokenForDEL(uint256,address)',[$amountRaw,$recipient],$limitRaw],'sellExactTokensForDEL'=>['sellExactTokensForDEL(uint256,uint256,address)',[$amountRaw,$limitRaw,$recipient],'0'],'sellTokensForExactDEL'=>['sellTokensForExactDEL(uint256,uint256,address)',[$amountRaw,$limitRaw,$recipient],'0'],default=>throw new \InvalidArgumentException('Unknown trade variant')};
        Units::uint($amountRaw);Units::uint($limitRaw);if($amountRaw==='0'||(in_array($kind,['buyExactTokenForDEL','sellTokensForExactDEL'],true)&&$limitRaw==='0'))throw new \InvalidArgumentException('Positive amount/cap required');
        return Contract::profile($token,'Token')->draft($sender,$spec[0],$spec[1],$spec[2]);
    }
    public function maintain(string $sender,string $token,string $field,string $value,string $profile='TestnetReserveToken20261029'): TransactionDraft
    {
        if($profile!=='TestnetReserveToken20261029')throw new \InvalidArgumentException('Maintenance requires the explicit observed reservable profile');
        $signature=match($field){'identity'=>'updateIdentity(string)','maxTotalSupply'=>'updateMaxTotalSupply(uint256)','minTotalSupply'=>'updateMinTotalSupply(uint256)',default=>throw new \InvalidArgumentException('Unknown maintenance field')};return Contract::profile($token,$profile)->draft($sender,$signature,[$value]);
    }
    private static function symbolBoundary(string $symbol): string { if(preg_match('//u',$symbol)!==1)throw new \InvalidArgumentException('Invalid UTF-8 symbol');return preg_replace('/\A[\p{Z}\x{0009}-\x{000D}\x{001C}-\x{001F}\x{0085}]+|[\p{Z}\x{0009}-\x{000D}\x{001C}-\x{001F}\x{0085}]+\z/u','',$symbol); }
    public static function creationCommissionWei(string $symbol): string { $symbol=self::symbolBoundary($symbol);if($symbol==='')throw new \InvalidArgumentException('Token symbol required');return Units::parse(match(mb_strlen($symbol,'UTF-8')){3=>'2500000',4=>'250000',5=>'25000',6=>'2500',default=>'250'}); }
    public static function creationReserveWei(string $symbol,string $extraWei='0'): string { Units::uint($extraWei);return Units::uint(bcadd(bcadd(Units::parse('1000'),self::creationCommissionWei($symbol),0),$extraWei,0)); }
    public function create(string $sender,string $name,string $symbol,string $initialMintRaw,string $minSupplyRaw,string $maxSupplyRaw,int $crr,string $identity,?string $reserveWei=null,?string $creator=null): TransactionDraft { if($name===''||self::symbolBoundary($symbol)==='')throw new \InvalidArgumentException('Token name and symbol required');return $this->center->draft($sender,'createToken((uint256,uint256,uint256,address,uint8,string,string,string))',[[$initialMintRaw,$minSupplyRaw,$maxSupplyRaw,$creator??$sender,(string)$crr,$identity,$symbol,$name]],$reserveWei??self::creationReserveWei($symbol)); }
    public function updateDetails(string $sender,string $token,string $identity,string $maxSupplyRaw): TransactionDraft { return Contract::profile($token,'Token')->draft($sender,'updateDetails(string,uint256)',[$identity,$maxSupplyRaw]); }
    public function tokenAddressBySymbol(string $symbol,string $block='latest'): ?string { $symbol=self::symbolBoundary($symbol);if($symbol==='')throw new \InvalidArgumentException('Token symbol required');foreach(array_unique([$symbol,strtolower($symbol),strtoupper($symbol)]) as $candidate){$address=$this->center->call($this->client,'tokens(string)',[$candidate],$block)[0];if($address!=='0x'.str_repeat('0',40))return TransactionDraft::address($address);}return null; }
    public function createdToken(array $receipt,?string $symbol=null): string { $matches=[];foreach(Events::receipt('TokenCenter',$receipt,$this->tokenCenter)as $event){if($event['event']==='TokenDeployed'&&($symbol===null||$event['args']['meta'][6]===$symbol))$matches[]=$event['args']['tokenAddress'];elseif($event['event']==='TokenReservelessDeployed')$matches[]=$event['args']['tokenAddress'];}if(count($matches)!==1||$matches[0]==='0x'.str_repeat('0',40))throw new \RuntimeException('Token creation event absent or ambiguous');return TransactionDraft::address($matches[0]); }
}
