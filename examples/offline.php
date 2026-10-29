<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Decimal\{Contract, TransactionDraft, Units};

// Public example addresses. This example never connects to a node.
$sender = '0x' . str_repeat('12', 20);
$recipient = '0x' . str_repeat('34', 20);
$tokenAddress = '0x' . str_repeat('56', 20);
$del = TransactionDraft::del($sender, $recipient, '0.01', 'hello');
$token = Contract::profile($tokenAddress, 'Token');
$transfer = $token->draft($sender, 'transfer(address,uint256)', [$recipient, Units::parse('2', 18)]);
$approve = $token->draft($sender, 'approve(address,uint256)', [$recipient, '0']);
echo json_encode(['del'=>$del->rpc(), 'erc20'=>$transfer->rpc(), 'revoke'=>$approve->rpc()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
