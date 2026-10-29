"""Independent eth-account vectors, using only publicly known test key 1."""
import json
from pathlib import Path
from eth_account import Account
ROOT=Path(__file__).resolve().parents[1]
KEY='0x'+'0'*63+'1'
rows=[]
for kind in [0,1,2]:
 for creation in [False,True]:
  for nonce in [0,1,128,65537]:
   tx=dict(nonce=nonce,gas=100000,value=nonce*123,chainId=202020,data=bytes.fromhex('60006000' if creation else '0001aabb'))
   if not creation:tx['to']='0x1212121212121212121212121212121212121212'
   if kind:tx.update(type=kind,accessList=[{'address':'0x3434343434343434343434343434343434343434','storageKeys':['0x'+'00'*32,'0x'+'00'*31+'01']}])
   tx.update(dict(maxFeePerGas=100,maxPriorityFeePerGas=3) if kind==2 else dict(gasPrice=7))
   signed=Account.sign_transaction(tx,KEY)
   rows.append(dict(type=kind,creation=creation,nonce=str(nonce),value=str(tx['value']),data='0x'+tx['data'].hex(),raw='0x'+signed.raw_transaction.hex(),hash='0x'+signed.hash.hex(),sender=Account.recover_transaction(signed.raw_transaction).lower()))
(ROOT/'tests'/'transaction-vectors.json').write_text(json.dumps(rows,indent=2)+'\n',newline='\n')
print(len(rows),'independent signed-transaction vectors')
