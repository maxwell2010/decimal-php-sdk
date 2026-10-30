<?php
declare(strict_types=1);
namespace Decimal;
final class Workflows
{
    public static function allowance(Client $client,string $token,string $spender,string $amount,TransactionDraft $action,bool $resetExisting=false): array
    {
        Units::uint($amount);$owner=TransactionDraft::address($action->from);$spender=TransactionDraft::address($spender);
        if($spender!==TransactionDraft::address($action->to)||$owner==='0x'.str_repeat('0',40)||$spender==='0x'.str_repeat('0',40))throw new \InvalidArgumentException('Invalid allowance action');
        $multicall=$client->network->chainId===75?'0x7b23eb47587ca6482fc16cb3d9d426ec64d4b5fc':'0x81fd5fae106dfd0343b0435a1bc0ef89bb14c317';
        if($spender===$multicall)throw new \InvalidArgumentException('Generic Multicall cannot receive ERC20 allowance');
        $contract=Contract::profile($token,'Token');$allowance=$contract->call($client,'allowance(address,address)',[$owner,$spender])[0];$steps=[];
        if(bccomp($allowance,$amount, 0)<0){
            if($resetExisting&&$allowance!=='0')$steps[]=$contract->draft($owner,'approve(address,uint256)',[$spender,'0']);
            $steps[]=$contract->draft($owner,'approve(address,uint256)',[$spender,$amount]);
        }
        return ['approvals'=>$steps,'action'=>$action];
    }
    public static function multisendDel(string $from,string $multicall,array $recipients,string $memo=''): TransactionDraft
    {
        if(!array_is_list($recipients)||!$recipients||!mb_check_encoding($memo,'UTF-8'))throw new \InvalidArgumentException('Recipients required');$total='0';$calls=[];
        foreach($recipients as $r){TransactionDraft::address($r['address']);Units::uint($r['amount']);if($r['amount']==='0')throw new \InvalidArgumentException('Positive amount required');$total=bcadd($total,$r['amount'], 0);$calls[]=[$r['address'],$r['amount'],'0x'];}
        Units::uint($total);if(count($calls)===1)return new TransactionDraft($from,$calls[0][0],$total,'0x'.bin2hex($memo));
        if($memo!=='')$calls[]=['0x'.str_repeat('0',40),'0','0x'.bin2hex($memo)];
        return Contract::profile($multicall,'Multicall')->draft($from,'aggregate((address,uint256,bytes)[])',[$calls],$total);
    }
    public static function safeMultisendData(array $calls): string
    {
        if(!$calls)throw new \InvalidArgumentException('Calls required');$out='0x';
        foreach($calls as $draft){if(!$draft instanceof TransactionDraft)throw new \InvalidArgumentException('Expected transaction drafts');$bytes=substr($draft->data,2);$out.='00'.substr(TransactionDraft::address($draft->to),2).str_pad(substr(Units::hex($draft->value),2),64,'0',STR_PAD_LEFT).str_pad(dechex(strlen($bytes)/2),64,'0',STR_PAD_LEFT).$bytes;}
        return $out;
    }
}
