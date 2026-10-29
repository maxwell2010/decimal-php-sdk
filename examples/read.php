<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Decimal\{Client, Network, Units};
$network = Network::testnet();
if ($url = getenv('DECIMAL_RPC_URL')) $network = new Network($network->chainId, $url, $network->contractCenter);
$address = $argv[1] ?? throw new InvalidArgumentException('Usage: php examples/read.php 0xADDRESS');
echo Units::format((new Client($network))->balance($address)), " DEL\n";
