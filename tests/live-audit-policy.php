<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/tools/live_rpc_common.php';
$policyAudit=new LiveRpcAudit('Offline policy test');
$policyClient=$policyAudit->client(new Decimal\Network(75,'http://unused','0x'.str_repeat('12',20)));
foreach(['eth_sendRawTransaction','eth_sendTransaction','eth_sign','personal_sign','eth_estimateGas'] as $method)rejects(fn()=>$policyClient->rpc->call($method,[]));
same($policyAudit->requests,[]); // Guard rejects before reaching the real SDK HTTP transport.
rejects(fn()=>$policyClient->rpc->call('eth_getLogs',[['address'=>'0x'.str_repeat('12',20),'fromBlock'=>'0x1','toBlock'=>'0x1f6']]));
rejects(fn()=>$policyClient->rpc->call('eth_getLogs',[['address'=>'0x'.str_repeat('12',20),'fromBlock'=>'0x1','toBlock'=>'0x1f5']]));
rejects(fn()=>$policyClient->rpc->call('eth_getLogs',[['fromBlock'=>'0x1','toBlock'=>'0x2']]));
same($policyAudit->requests,[]);
same($policyAudit->exitCode(),0);$policyAudit->check('intentional offline failure',fn()=>throw new RuntimeException('offline failure'));same($policyAudit->exitCode(),1);
echo "Live audit guard blocks signing, sends, estimates and unbounded logs before HTTP\n";
