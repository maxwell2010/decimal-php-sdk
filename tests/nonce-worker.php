<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
if($argc!==3)throw new RuntimeException('Expected store and count');
$manager=new Decimal\NonceManager($argv[1]);$out=[];
for($i=0;$i<(int)$argv[2];++$i)$out[]=$manager->reserve(202020,'0x'.str_repeat('12',20),'0');
echo json_encode($out,JSON_THROW_ON_ERROR);
