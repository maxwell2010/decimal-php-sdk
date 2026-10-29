<?php
declare(strict_types=1);
namespace Decimal;
/** Pinned stateless ValidateBasic predicates. Roles, balances and ante execution remain node concerns. */
final class CosmosValidation
{
    private static function require(bool $ok,string $rule): void { if(!$ok)throw new \InvalidArgumentException('Cosmos validation: '.$rule); }
    private static function integer(mixed $v,int $bits=256): string
    {
        self::require(is_string($v)&&preg_match('/^[+-]?[0-9]+$/D',$v)===1,'integer');
        $n=gmp_init($v,10);self::require(strlen(gmp_strval(gmp_abs($n),2))<=$bits,'integer size');return gmp_strval($n,10);
    }
    private static function address(mixed $v,string $hrp='d0'): void { self::require(is_string($v),'address string');Address::fromBech32Bytes($v,$hrp); }
    private static function unique(array $list,bool $nonempty=false): void { self::require((!$nonempty||count($list)>0)&&count(array_unique($list,SORT_STRING))===count($list),'unique subtoken IDs'); }
    private static function positive(mixed $v): void { self::require(bccomp(self::integer($v),'0',0)>0,'positive integer'); }
    private static function decimalDenom(mixed $v): bool { return is_string($v)&&preg_match('/^[a-z][a-z0-9]{2,9}$/D',$v)===1; }
    private static function sdkDenom(mixed $v): bool
    {
        if(!is_string($v))return false;
        static $pattern=null;if($pattern===null){$list=json_decode(file_get_contents(dirname(__DIR__).'/resources/cosmos/validation-denom-whitelist.json'),true);$pattern='~^[a-zA-Z][a-zA-Z0-9/:._-]{2,127}|'.implode('|',array_map(static fn($s)=>preg_quote($s,'~'),$list)).'$~D';}
        return preg_match($pattern,$v)===1;
    }
    private static function coin(array $c,bool $sdk=false): void
    {
        self::require($sdk?self::sdkDenom($c['denom']??''):self::decimalDenom($c['denom']??''),'denomination');self::positive($c['amount']??'');
    }
    public static function supported(): array { return json_decode(file_get_contents(dirname(__DIR__).'/resources/cosmos/inventory.json'),true,512,JSON_THROW_ON_ERROR); }
    public static function validate(string $type,array $m): void
    {
        self::require(in_array($type,self::supported(),true),'unsupported message policy');(new Protobuf())->encode($type,$m);
        $rules=json_decode(file_get_contents(dirname(__DIR__).'/resources/cosmos/signer-rules.json'),true,512,JSON_THROW_ON_ERROR);$rule=$rules[$type];self::address($m[$rule['field']]??'',$rule['hrp']);
        $name=substr($type,strrpos($type,'.')+1);
        if(str_starts_with($type,'/decimal.coin.v1.')){self::coinMessage($name,$m);return;}
        if(str_starts_with($type,'/decimal.validator.v1.')){self::validatorMessage($name,$m);return;}
        if(str_starts_with($type,'/decimal.nft.v1.')){
            if($name==='MsgMintToken'){
                self::address($m['recipient']??'');foreach(['denom','token_id'] as $f)self::require(preg_match('/^[a-zA-Z0-9_-]{1,255}$/D',$m[$f]??'')===1,'collection identifier');
                self::require(($m['quantity']??'0')!=='0','quantity');self::positive($m['reserve']['amount']??'');return;
            }
            self::require(preg_match('/^[\s\p{Z}]*$/u',$m['token_id']??'')!==1,'token ID');if($name!=='MsgUpdateToken')self::unique($m['sub_token_ids']??[]);
            if($name==='MsgUpdateReserve')self::require(self::integer($m['reserve']['amount']??'')!=='0','nonzero reserve');
            if($name==='MsgSendToken'){self::address($m['recipient']??'');self::require(Address::fromBech32Bytes($m['sender'])!==Address::fromBech32Bytes($m['recipient']),'NFT self transfer');}return;
        }
        if(str_starts_with($type,'/decimal.legacy.v1.')){self::require(strlen(substr($m['public_key']??'',2))===66,'33-byte legacy public key');return;}
        if(str_starts_with($type,'/decimal.fee.v1.')){
            $prices=$m['prices']??[];self::require(count($prices)>0,'prices');$seen=[];
            foreach($prices as $p){self::require(bccomp(self::integer($p['price']??'',315),'0',0)>0,'positive SDK decimal price');$key=json_encode([$p['denom']??'',$p['quote']??'']);self::require(!isset($seen[$key]),'duplicate price pair');$seen[$key]=true;}return;
        }
        if(str_starts_with($type,'/decimal.multisig.v1.')){
            if($name==='MsgCreateTransaction'){self::address($m['wallet']??'');return;}
            if($name==='MsgSignTransaction'){Address::fromBech32Bytes($m['id']??'','d0mstx',false);return;}
            $owners=$m['owners']??[];$weights=$m['weights']??[];self::require(count($owners)>=2&&count($owners)<=16&&count($owners)===count($weights)&&count(array_unique($owners))===count($owners),'multisig owners');
            $sum='0';foreach($owners as $i=>$o){self::address($o);self::require(bccomp($weights[$i],'1',0)>=0&&bccomp($weights[$i],'1024',0)<=0,'weight');$sum=bcadd($sum,$weights[$i],0);}self::require(bccomp($sum,$m['threshold']??'0',0)>=0,'threshold');return;
        }
        if(str_starts_with($type,'/decimal.swap.v1.')){
            if(in_array($name,['MsgActivateChain','MsgDeactivateChain'],true)){self::require(($m['id']??'0')!=='0','chain ID');if($name==='MsgActivateChain')self::require(($m['name']??'')!=='','chain name');return;}
            self::positive($m['amount']??'');self::require(($m['from_chain']??'0')!=='0'&&($m['dest_chain']??'0')!=='0'&&$m['from_chain']!==$m['dest_chain'],'swap chains');self::integer($m['transaction_number']??'');
            if($name==='MsgRedeemSwap'){self::address($m['recipient']??'');foreach(['r','s'] as $f)self::require(preg_match('/^[a-fA-F0-9]{64}$/D',$m[$f]??'')===1,'swap 32-byte hex');}return;
        }
        if(str_starts_with($type,'/cosmos.upgrade.v1beta1.')){
            if($name==='MsgCancelUpgrade')return;$p=$m['plan']??[];self::require(($p['name']??'')!==''&&bccomp($p['height']??'0','0',0)>0,'upgrade name/height');self::require(!array_key_exists('upgraded_client_state',$p),'deprecated upgraded client state');
            if(isset($p['time']))self::require(($p['time']['seconds']??'0')==='-62135596800'&&($p['time']['nanos']??'0')==='0','deprecated timed upgrade');return;
        }
        throw new \InvalidArgumentException('Unsupported message policy');
    }
    private static function coinMessage(string $n,array $m): void
    {
        if($n==='MsgCreateCoin'||$n==='MsgUpdateCoin'){
            self::require(self::decimalDenom($m['denom']??''),'coin denom');$limit=self::integer($m['limit_volume']??'');$max=bcpow('10','33',0);$min=bcpow('10','18',0);self::require(bccomp($limit,$max,0)<=0,'limit volume');
            if(($m['min_volume']??'')!==''&&$m['min_volume']!=='0'){$v=self::integer($m['min_volume']);self::require($v==='0'||(bccomp($v,$min,0)>=0&&bccomp($v,$max,0)<=0&&bccomp($v,$limit,0)<=0),'min volume');}
            if($n==='MsgCreateCoin'){self::require(strlen($m['title']??'')<=64,'title length');self::require(bccomp($m['crr']??'0','10',0)>=0&&bccomp($m['crr']??'0','100',0)<=0,'crr');$initial=self::integer($m['initial_volume']??'');self::require(bccomp($initial,$min,0)>=0&&bccomp($initial,$max,0)<=0&&bccomp($initial,$limit,0)<=0,'initial volume');self::require(bccomp(self::integer($m['initial_reserve']??''),$min,0)>=0,'initial reserve');}return;
        }
        if($n==='MsgRedeemCheck')return;
        if($n==='MsgSendCoin'||$n==='MsgMultiSendCoin'){$sends=$n==='MsgSendCoin'?[$m]:($m['sends']??[]);self::require(count($sends)>0,'empty sends');foreach($sends as $s){self::address($s['recipient']??'');self::require($m['sender']!==$s['recipient'],'self transfer');self::coin($s['coin']??[]);}return;}
        if($n==='MsgBurnCoin'){self::coin($m['coin']??[]);return;}
        if($n==='MsgBuyCoin'){self::require(($m['coin_to_buy']['denom']??'')!==($m['max_coin_to_sell']['denom']??''),'same coin');self::coin($m['coin_to_buy']??[]);self::coin($m['max_coin_to_sell']??[]);return;}
        if($n==='MsgSellCoin'){self::require(($m['coin_to_sell']['denom']??'')!==($m['min_coin_to_buy']['denom']??''),'same coin');self::coin($m['coin_to_sell']??[]);self::require(self::decimalDenom($m['min_coin_to_buy']['denom']??''),'buy denom');return;}
        if($n==='MsgSellAllCoin'){self::require(self::decimalDenom($m['coin_denom_to_sell']??'')&&($m['coin_denom_to_sell']??'')!==($m['min_coin_to_buy']['denom']??''),'sell denom');self::coin($m['min_coin_to_buy']??[]);return;}
        throw new \InvalidArgumentException('Unsupported coin policy');
    }
    private static function validatorMessage(string $n,array $m): void
    {
        if($n==='MsgHaltChain'){self::require(bccomp($m['height']??'0','0',0)>=0,'halt height');return;}
        if(in_array($n,['MsgResumeChain','MsgFreezeChain','MsgUnfreezeChain','MsgSetOnline','MsgSetOffline'],true))return;
        if($n==='MsgCreateValidator'||$n==='MsgEditValidator'){
            self::address($m['reward_address']??'');self::require(count(array_filter($m['description']??[],static fn($v)=>$v!==''))>0,'description');
            if($n==='MsgCreateValidator'){self::require(($m['consensus_pubkey']['value']??'0x')!=='0x','consensus pubkey');self::require(bccomp(self::integer($m['commission']??'',315),'0',0)>=0,'commission');self::coin($m['stake']??[],true);}return;
        }
        if(str_contains($n,'Redelegation')||str_contains($n,'Redelegate')){self::address($m['validator_src']??'','d0valoper');self::address($m['validator_dst']??'','d0valoper');if(!str_starts_with($n,'MsgCancel'))self::require($m['validator_src']!==$m['validator_dst'],'self redelegation');}
        else self::address($m['validator']??'','d0valoper');
        if(str_starts_with($n,'MsgCancel'))self::require(bccomp($m['creation_height']??'0','0',0)>0,'creation height');
        if(str_ends_with($n,'NFT')){self::require(($m['token_id']??'')!=='','token ID');self::unique($m['sub_token_ids']??[],true);return;}self::coin($m['coin']??[],true);
    }
}
