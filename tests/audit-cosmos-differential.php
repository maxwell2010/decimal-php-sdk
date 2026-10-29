<?php
declare(strict_types=1);
$cosmosDifferential=json_decode(file_get_contents(dirname(__DIR__).'/resources/cosmos/differential-vectors.json'),true,512,JSON_THROW_ON_ERROR);
$cosmosDiffCodec=new Decimal\Protobuf();
same($cosmosDifferential['message_count'],393);
foreach($cosmosDifferential['vectors'] as $row){
    if(!$row['initialized']){rejects(fn()=>$cosmosDiffCodec->encode($row['type'],$row['values']));rejects(fn()=>$cosmosDiffCodec->decode($row['type'],hex2bin($row['hex'])));continue;}
    same(bin2hex($cosmosDiffCodec->encode($row['type'],$row['values'])),$row['hex']);
    $raw=hex2bin($row['hex']);same($cosmosDiffCodec->encode($row['type'],$cosmosDiffCodec->decode($row['type'],$raw)),$raw);
}
echo "Independent Cosmos field/presence differential vectors passed\n";
