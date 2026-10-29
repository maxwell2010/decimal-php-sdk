"""Independent descriptor field and presence vectors using Python protobuf."""
import argparse,hashlib,json
from pathlib import Path
from google.protobuf import descriptor_pb2,descriptor_pool,message_factory
ROOT=Path(__file__).resolve().parents[1]
def render(path):
 raw=path.read_bytes();files=descriptor_pb2.FileDescriptorSet.FromString(raw);pool=descriptor_pool.DescriptorPool();pending=list(files.file)
 while pending:
  left=[]
  for f in pending:
   try:pool.AddSerializedFile(f.SerializeToString())
   except (TypeError,ValueError):left.append(f)
  if len(left)==len(pending):raise ValueError('Unresolved descriptors')
  pending=left
 schema=json.loads((ROOT/'resources/cosmos/schema.json').read_text());rows=[]
 def scalar(f,zero=False):
  if f.type==8:return False if zero else True
  if f.type==9:return '' if zero else 'x'
  if f.type==12:return b'' if zero else b'\0\x01'
  if f.type==14:return f.enum_type.values[0].number if zero else next((v.number for v in f.enum_type.values if v.number),f.enum_type.values[0].number)
  if f.type in (1,2):return 0.0 if zero else 1.25
  return 0 if zero else 127
 def php(v,f):return '0x'+v.hex() if f.type==12 else v if f.type in (8,9,1,2) else str(v)
 def child(desc):
  obj=message_factory.GetMessageClass(desc)();out={}
  for field in desc.fields:
   if field.type!=11 and not field.is_repeated:
    v=scalar(field);setattr(obj,field.name,v);out[field.name]=php(v,field);break
  return obj,out
 for name in sorted(schema):
  desc=pool.FindMessageTypeByName(name)
  if not desc.fields:rows.append(dict(type=name,values={},hex='',initialized=not any(f.is_required for f in desc.fields)))
  for f in desc.fields:
   for zero in [False,True]:
    obj=message_factory.GetMessageClass(desc)();values={}
    if f.type==11:
     nested,nestedphp=child(f.message_type)
     if zero:nested=message_factory.GetMessageClass(f.message_type)();nestedphp={}
     if f.is_repeated:
      if f.message_type.GetOptions().map_entry:continue
      getattr(obj,f.name).add().CopyFrom(nested);values[f.name]=[nestedphp]
     else:getattr(obj,f.name).CopyFrom(nested);values[f.name]=nestedphp
    else:
     v=scalar(f,zero)
     if f.is_repeated:getattr(obj,f.name).append(v);values[f.name]=[php(v,f)]
     else:setattr(obj,f.name,v);values[f.name]=php(v,f)
    rows.append(dict(type=name,values=values,hex=obj.SerializePartialToString(deterministic=True).hex(),initialized=obj.IsInitialized()))
 return dict(descriptor_sha256=hashlib.sha256(raw).hexdigest(),message_count=len(schema),vectors=rows)
if __name__=='__main__':
 parser=argparse.ArgumentParser(__doc__);parser.add_argument('descriptor',type=Path);args=parser.parse_args();out=render(args.descriptor);(ROOT/'resources/cosmos/differential-vectors.json').write_text(json.dumps(out,separators=(',',':'))+'\n',encoding='utf-8',newline='\n');print(len(out['vectors']),'vectors across',out['message_count'],'messages')

