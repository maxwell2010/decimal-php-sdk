<?php
declare(strict_types=1);
namespace Decimal;
/** Ordered receipt DTOs preserve duplicate NFT movements and token/amount pairing. */
final class NftReceipts
{
 public static function movements(array $receipt,string $collection): array
 {
  $events=self::events($receipt,$collection);$out=[];
  foreach($events as $e){$a=$e['args'];if($e['event']==='Transfer')$out[]=['from'=>$a['from'],'to'=>$a['to'],'operator'=>null,'tokenIds'=>[$a['tokenId']],'amounts'=>['1']];
  elseif($e['event']==='TransferSingle')$out[]=['from'=>$a['from'],'to'=>$a['to'],'operator'=>$a['operator'],'tokenIds'=>[$a['id']],'amounts'=>[$a['value']]];
  elseif($e['event']==='TransferBatch'){if(count($a['ids'])!==count($a['values']))throw new \RuntimeException('NFT ID/amount mismatch');$out[]=['from'=>$a['from'],'to'=>$a['to'],'operator'=>$a['operator'],'tokenIds'=>$a['ids'],'amounts'=>$a['values']];}}
  return $out;
 }
 public static function uriUpdates(array $receipt,string $collection): array
 { $out=[];foreach(self::events($receipt,$collection) as $event)if($event['event']==='URI'){$a=$event['args'];$out[]=['tokenId'=>$a['id'],'uri'=>$a['value']];}return $out; }
 public static function created(array $receipt,string $center): array
 { $out=[];foreach(Events::receipt('TestnetNFTCenter20261029',$receipt,$center) as $event)if($event['event']==='NFTCreated')$out[]=$event['args'];return $out; }
 private static function events(array $receipt,string $collection): array
 {
  if(($receipt['status']??null)!=='0x1')throw new \InvalidArgumentException('Successful receipt required');TransactionDraft::address($collection);
  $abi=[];foreach(['ERC721','ERC1155'] as $profile)$abi=[...$abi,...json_decode(file_get_contents(dirname(__DIR__).'/abi/'.$profile.'.json'),true,512,JSON_THROW_ON_ERROR)];
  $logs=array_values(array_filter($receipt['logs']??[],static fn($log)=>strtolower($log['address']??'')===strtolower($collection)));usort($logs,static fn($a,$b)=>bccomp(Units::decimal($a['logIndex']??'0x0'),Units::decimal($b['logIndex']??'0x0'),0));
  $abi=array_values(array_filter($abi,static fn($entry)=>($entry['type']??'')==='event'&&in_array($entry['name'],['Transfer','TransferSingle','TransferBatch','URI'],true)));
  $receipt['logs']=$logs;return Events::receiptWithAbi($abi,$receipt,$collection);
 }
}
