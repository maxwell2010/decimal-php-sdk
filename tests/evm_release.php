<?php
declare(strict_types=1);
use Decimal\{SafePolicy,SafeTransaction,Wallet,TransactionDraft,VerifiedContract,Client,Network,Rpc,AbiCodec,Contract,StakingReader};
$evmOwnerA=new Wallet(str_pad('1',64,'0',STR_PAD_LEFT));$evmOwnerB=new Wallet(str_pad('2',64,'0',STR_PAD_LEFT));$evmSafe='0x'.str_repeat('12',20);$evmCodec=new AbiCodec();
$evmPolicy=new SafePolicy($evmSafe,202020,'0x10','3',[$evmOwnerA->address=>'10',$evmOwnerB->address=>'1'],'10');
$evmTx=$evmPolicy->transaction(new TransactionDraft($evmSafe,$evmOwnerB->address,'2'));
same($evmPolicy->pack($evmTx,[$evmTx->sign($evmOwnerA)]),$evmTx->sign($evmOwnerA));
rejects(fn()=>$evmPolicy->pack($evmTx,[$evmTx->sign($evmOwnerB)]));rejects(fn()=>$evmPolicy->pack($evmTx,[$evmTx->sign($evmOwnerA),$evmTx->sign($evmOwnerA)]));
rejects(fn()=>new SafePolicy($evmSafe,202020,'0x10','3',[$evmOwnerA->address=>'1'],'2'));
$evmBlocks=[];$evmFrozen=[$evmSafe,$evmOwnerA->address,$evmSafe,'4','0','0','0'];
$evmRpc=new Rpc('http://unused',function($r)use($evmCodec,$evmOwnerA,$evmOwnerB,$evmTx,&$evmBlocks,&$evmFrozen){
 $method=$r['method'];$result=match($method){'eth_chainId'=>'0x31524','eth_getBlockByNumber'=>['timestamp'=>'0x64'],default=>null};
 if($method==='eth_call'){
  $evmBlocks[]=$r['params'][1];$selector=substr($r['params'][0]['data'],0,10);
  $result=match($selector){
   $evmCodec->encodeFunctionSignature('getOwnersWithWeights()')=>$evmCodec->encodeParameters(['(address,uint256)[]'],[[[$evmOwnerA->address,'10'],[$evmOwnerB->address,'1']]]),
   $evmCodec->encodeFunctionSignature('getWeightThreshold()')=>$evmCodec->encodeParameters(['uint256'],['10']),
   $evmCodec->encodeFunctionSignature('nonce()')=>$evmCodec->encodeParameters(['uint256'],['3']),
   $evmCodec->encodeFunctionSignature('getTransactionHash(address,uint256,bytes,uint8,uint256,uint256,uint256,address,address,uint256)')=>$evmCodec->encodeParameters(['bytes32'],[$evmTx->hash()]),
   $evmCodec->encodeFunctionSignature('getFrozenStake(uint256)')=>$evmCodec->encodeParameters(['((address,address,address,uint256,uint256,uint8,uint256),uint8,uint8,uint256)'],[[$evmFrozen,'1','1','99']]),
   default=>throw new RuntimeException('Unexpected test call')};
 }
 return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];
});
$evmClient=new Client(Network::testnet(),$evmRpc);$evmRead=SafePolicy::read($evmClient,$evmSafe,'0x10');same($evmRead->threshold,'10');same($evmRead->nonce,'3');
same($evmRead->draft($evmClient,$evmTx,$evmOwnerB->address,[$evmTx->sign($evmOwnerA)])->to,$evmSafe);same(array_unique($evmBlocks),['0x10']);
$evmReader=new StakingReader($evmClient,Contract::profile($evmSafe,'Delegation'));
$evmRow=$evmReader->frozen('1',$evmOwnerA->address,'0x10');same($evmRow->active(),true);same($evmRow->matured('98'),false);same($evmRow->matured('99'),true);
rejects(fn()=>$evmReader->frozen('1',$evmOwnerB->address,'0x10'));same($evmReader->completeChecked($evmOwnerA->address,['1'],'0x10')->to,$evmSafe);
$evmFrozen[3]='0';rejects(fn()=>$evmReader->completeChecked($evmOwnerA->address,['1'],'0x10'));
$evmVersion='0x6000';$evmCodeRpc=new Rpc('http://unused',function($r)use(&$evmVersion){return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$r['method']==='eth_chainId'?'0x31524':$evmVersion];});
$evmBound=new VerifiedContract(new Client(Network::testnet(),$evmCodeRpc),$evmSafe,'Token','0x'.kornrunner\Keccak::hash(hex2bin('6000'),256));
$evmVersion='0x6001';rejects(fn()=>$evmBound->draft($evmOwnerA->address,'transfer(address,uint256)',[$evmOwnerB->address,'1']));
echo "Weighted Safe, deployment and frozen-stake checks passed\n";
$evmNftCalls=[];$evmNftOwner=$evmOwnerA->address;
$evmNftRpc=new Rpc('http://unused',function($r)use($evmCodec,&$evmNftCalls,&$evmNftOwner){
 $evmNftCalls[]=$r['method'];$result='0x31524';
 if($r['method']==='eth_call'){
  $s=substr($r['params'][0]['data'],0,10);
  $result=match($s){
   $evmCodec->encodeFunctionSignature('ownerOf(uint256)')=>$evmCodec->encodeParameters(['address'],[$evmNftOwner]),
   $evmCodec->encodeFunctionSignature('getApproved(uint256)')=>$evmCodec->encodeParameters(['address'],['0x'.str_repeat('0',40)]),
   $evmCodec->encodeFunctionSignature('isApprovedForAll(address,address)')=>$evmCodec->encodeParameters(['bool'],[false]),
   $evmCodec->encodeFunctionSignature('balanceOf(address,uint256)')=>$evmCodec->encodeParameters(['uint256'],['5']),
   default=>throw new RuntimeException('Unexpected NFT read')};
 }
 return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];
});
$evmNft=new Decimal\NftService(new Client(Network::testnet(),$evmNftRpc),$evmSafe);
same($evmNft->transfer721($evmOwnerA->address,$evmOwnerB->address,'1')->to,$evmSafe);rejects(fn()=>$evmNft->transfer721($evmOwnerB->address,$evmSafe,'1'));
same(count($evmNft->delegate721($evmOwnerA->address,$evmOwnerB->address,$evmSafe,'1')['approvals']),1);
same(count($evmNft->delegate1155($evmOwnerA->address,$evmOwnerB->address,$evmSafe,'1','3')['approvals']),1);
rejects(fn()=>$evmNft->transfer1155($evmOwnerA->address,$evmSafe,'1','6'));same(in_array('eth_sendRawTransaction',$evmNftCalls,true),false);
$evmDeployed=new Decimal\DeployedNft($evmClient,$evmSafe,721);same(substr($evmDeployed->mint($evmOwnerA->address,$evmOwnerB->address,'ipfs://test','100')->data,0,10),'0xb125b73f');
$evmDeployed1155=new Decimal\DeployedNft($evmClient,$evmSafe,1155);same(substr($evmDeployed1155->mint($evmOwnerA->address,$evmOwnerB->address,'ipfs://test','100','7','2')->data,0,10),'0x759901a3');
rejects(fn()=>$evmDeployed1155->mint($evmOwnerA->address,$evmOwnerB->address,'ipfs://test','100','7','0'));
same(substr(Decimal\DeployedNft::createCollection($evmSafe,721,$evmOwnerA->address,$evmOwnerA->address,'TEST','Test','ipfs://meta',true,false)->data,0,10),'0xb307f97f');
$evmCreatedAbi=json_decode(file_get_contents('abi/TestnetNFTCenter20261029.json'),true);$evmCreatedEntry=array_values(array_filter($evmCreatedAbi,fn($e)=>($e['name']??'')==='NFTCreated'))[0];
$evmLog=['address'=>$evmSafe,'topics'=>['0x'.kornrunner\Keccak::hash(Contract::signature($evmCreatedEntry),256),$evmCodec->encodeParameters(['uint8'],['0'])],'data'=>$evmCodec->encodeParameters(['address','(address,string,string,string,bool,bool)'],[$evmOwnerB->address,[$evmOwnerA->address,'TEST','Test','ipfs://meta',true,false]])];
$evmCreated=Decimal\DeployedNft::createdCollection(['status'=>'0x1','logs'=>[$evmLog]],$evmSafe,$evmOwnerA->address,'TEST');same($evmCreated['address'],$evmOwnerB->address);same($evmCreated['refundable'],true);same($evmCreated['burnable'],false);
rejects(fn()=>Decimal\DeployedNft::createdCollection(['status'=>'0x1','logs'=>[$evmLog,$evmLog]],$evmSafe,$evmOwnerA->address,'TEST'));
$evmTokenSimulations=0;
$evmTokenRpc=new Rpc('http://unused',function($r)use($evmCodec,&$evmTokenSimulations){
 $result='0x31524';if($r['method']==='eth_call'){
  $s=substr($r['params'][0]['data'],0,10);$result=match($s){
   $evmCodec->encodeFunctionSignature('cap()')=>$evmCodec->encodeParameters(['uint256'],['100']),
   $evmCodec->encodeFunctionSignature('totalSupply()')=>$evmCodec->encodeParameters(['uint256'],['90']),
   $evmCodec->encodeFunctionSignature('balanceOf(address)')=>$evmCodec->encodeParameters(['uint256'],['10']),
   default=>'0x'};if($result==='0x')++$evmTokenSimulations;
 }return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];
});
$evmTokenClient=new Client(Network::testnet(),$evmTokenRpc);$evmToken=new Decimal\TokenService($evmTokenClient,$evmSafe);
same($evmToken->mint($evmOwnerA->address,$evmOwnerB->address,'10')->to,$evmSafe);rejects(fn()=>$evmToken->mint($evmOwnerA->address,$evmOwnerB->address,'11'));
same($evmToken->burn($evmOwnerA->address,'10')->to,$evmSafe);rejects(fn()=>$evmToken->burn($evmOwnerA->address,'11'));same($evmTokenSimulations,2);
same(Decimal\TokenService::createReserveless($evmTokenClient,$evmSafe,$evmOwnerA->address,'Test','TEST',true,true,'1','100','{}')->value,'0');
rejects(fn()=>Decimal\TokenService::createReserveless($evmTokenClient,$evmSafe,$evmOwnerA->address,'Test','TEST',true,true,'101','100','{}'));
$evmMixedTx=$evmTx;$evmMagic='0x1626ba7e';$evmApproval='1';$evmMixedReads=[];
$evmMixedRpc=new Rpc('http://unused',function($r)use($evmCodec,&$evmMixedTx,&$evmMagic,&$evmApproval,&$evmMixedReads){
 $result='0x31524';if($r['method']==='eth_getCode')$result='0x6000';if($r['method']==='eth_getStorageAt')$result=$evmCodec->encodeParameters(['address'],['0x'.str_repeat('0',40)]);
 if($r['method']==='eth_call'){
  $evmMixedReads[]=$r['params'][1];$data=$r['params'][0]['data'];
  if(str_starts_with($data,$evmCodec->encodeFunctionSignature('getTransactionHash(address,uint256,bytes,uint8,uint256,uint256,uint256,address,address,uint256)')))$result=$evmCodec->encodeParameters(['bytes32'],[$evmMixedTx->hash()]);
  elseif(str_starts_with($data,$evmCodec->encodeFunctionSignature('approvedHashes(address,bytes32)')))$result=$evmCodec->encodeParameters(['uint256'],[$evmApproval]);
  else $result=$evmCodec->encodeParameters(['bytes4'],[$evmMagic]);
 }return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];
});
$evmMixedClient=new Client(Network::testnet(),$evmMixedRpc);$evmMixedPolicy=new SafePolicy($evmSafe,202020,'0x10','3',[$evmOwnerA->address=>'10',$evmOwnerB->address=>'1'],'11');
$evmPersonal=$evmOwnerA->signMessage(hex2bin(substr($evmTx->hash(),2)));
$evmMixedRows=[['signer'=>$evmOwnerA->address,'kind'=>'personal','signature'=>$evmPersonal],['signer'=>$evmOwnerB->address,'kind'=>'contract','signature'=>'0xaabbcc','interface'=>'bytes32']];
$evmPacked=Decimal\SafeSignatures::pack($evmMixedClient,$evmMixedPolicy,$evmTx,$evmMixedRows,$evmOwnerA->address);
$evmExpected='0x'.str_pad(substr($evmOwnerB->address,2),64,'0',STR_PAD_LEFT).str_pad('82',64,'0',STR_PAD_LEFT).'00'.substr($evmPersonal,2,128).dechex(hexdec(substr($evmPersonal,-2))+4).str_pad('3',64,'0',STR_PAD_LEFT).'aabbcc';
same($evmPacked,$evmExpected);same(array_unique($evmMixedReads),['0x10']);
$evmMagic='0xffffffff';rejects(fn()=>Decimal\SafeSignatures::pack($evmMixedClient,$evmMixedPolicy,$evmTx,$evmMixedRows,$evmOwnerA->address));$evmMagic='0x20c13b0b';$evmMixedRows[1]['interface']='bytes';same(strlen(Decimal\SafeSignatures::pack($evmMixedClient,$evmMixedPolicy,$evmTx,$evmMixedRows,$evmOwnerA->address)),strlen($evmExpected));
$evmMixedRows[1]=['signer'=>$evmOwnerB->address,'kind'=>'prevalidated'];same(strlen(Decimal\SafeSignatures::pack($evmMixedClient,$evmMixedPolicy,$evmTx,$evmMixedRows,$evmOwnerA->address)),262);$evmApproval='0';rejects(fn()=>Decimal\SafeSignatures::pack($evmMixedClient,$evmMixedPolicy,$evmTx,$evmMixedRows,$evmOwnerA->address));
$evmBigPolicy=new SafePolicy($evmSafe,202020,'0x10','3',[$evmOwnerA->address=>'100000000000000000000000000000000000000000'],'100000000000000000000000000000000000000000');same($evmBigPolicy->pack($evmTx,[$evmTx->sign($evmOwnerA)]),$evmTx->sign($evmOwnerA));
$evmDelegateMessage=$evmTx->message;$evmDelegateMessage['operation']='1';$evmDelegateMessage['value']='0';$evmDelegateMessage['to']=$evmOwnerB->address;$evmMixedTx=new SafeTransaction($evmSafe,202020,$evmDelegateMessage);
$evmDelegateRows=[['signer'=>$evmOwnerA->address,'kind'=>'typed','signature'=>$evmMixedTx->sign($evmOwnerA)]];
rejects(fn()=>Decimal\SafeSignatures::draft($evmMixedClient,$evmPolicy,$evmMixedTx,$evmOwnerA->address,$evmDelegateRows));
$evmReview=['address'=>$evmOwnerB->address,'runtimeHash'=>'0x'.kornrunner\Keccak::hash(hex2bin('6000'),256)];same(Decimal\SafeSignatures::draft($evmMixedClient,$evmPolicy,$evmMixedTx,$evmOwnerA->address,$evmDelegateRows,$evmReview)->to,$evmSafe);
$evmCheckVector=json_decode(file_get_contents(__DIR__.'/checks-vectors.json'),true);$evmPasswordWallet=Decimal\ChecksService::passwordWallet($evmCheckVector['password']);same($evmPasswordWallet->address,$evmCheckVector['address']);same(Decimal\ChecksService::redeemMessageHash($evmCheckVector['check'],$evmCheckVector['redeemer']),$evmCheckVector['message']);same(Decimal\ChecksService::redeemDigest($evmCheckVector['check'],$evmCheckVector['redeemer']),$evmCheckVector['digest']);same($evmPasswordWallet->signHash($evmCheckVector['digest']),$evmCheckVector['signature']);rejects(fn()=>Decimal\ChecksService::passwordWallet(''));
$evmNonceFrom=null;$evmChecksRpc=new Rpc('http://unused',function($r)use($evmCodec,&$evmNonceFrom){$result='0x31524';if($r['method']==='eth_call'){$evmNonceFrom=$r['params'][0]['from'];$result=$evmCodec->encodeParameters(['uint256'],['7']);}return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];});
$evmChecks=new Decimal\ChecksService(new Client(Network::testnet(),$evmChecksRpc),$evmSafe);$evmCreateChecks=$evmChecks->createDel($evmOwnerA->address,['a','b'],'4','100');same($evmCreateChecks->value,'8');same($evmNonceFrom,$evmOwnerA->address);same(substr($evmChecks->redeem($evmCheckVector['redeemer'],[$evmCheckVector['password']],[$evmCheckVector['check']])->data,0,10),$evmCodec->encodeFunctionSignature('redeemChecks(bytes[],bytes32[])'));
$evmHighSig=substr($evmTx->sign($evmOwnerA),0,66).str_repeat('f',64).'1b';rejects(fn()=>Decimal\SafeSignatures::validateEoa($evmHighSig));
$evmMagic='0x1626ba7e';$evmThirdOwner=(new Wallet(str_pad('3',64,'0',STR_PAD_LEFT)))->address;
$evmThirdPolicy=new SafePolicy($evmSafe,202020,'0x10','3',[$evmOwnerA->address=>'10',$evmOwnerB->address=>'1',$evmThirdOwner=>'1'],'12');
$evmTriple=Decimal\SafeSignatures::pack($evmMixedClient,$evmThirdPolicy,$evmTx,[['signer'=>$evmOwnerA->address,'kind'=>'typed','signature'=>$evmTx->sign($evmOwnerA)],['signer'=>$evmOwnerB->address,'kind'=>'contract','signature'=>'0xaabb','interface'=>'bytes32'],['signer'=>$evmThirdOwner,'kind'=>'contract','signature'=>'0xcc','interface'=>'bytes32']],$evmOwnerA->address);
same(substr($evmTriple,66,64),str_pad('c3',64,'0',STR_PAD_LEFT));same(substr($evmTriple,196,64),str_pad('e5',64,'0',STR_PAD_LEFT));
