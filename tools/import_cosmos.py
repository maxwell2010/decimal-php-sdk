"""Import pinned Cosmos descriptors, resolving proto3 default packed fields.

Usage: python tools/import_cosmos.py path/to/decimal.pb [--check]
Requires the Python protobuf package for this development-time importer only. PHP runtime
uses generated JSON resources and never needs the original descriptor path.
"""
import argparse
import hashlib
import json
from pathlib import Path
from google.protobuf import descriptor_pb2, descriptor_pool, message_factory

ROOT = Path(__file__).resolve().parents[1]
PACKABLE = {1, 2, 3, 4, 5, 6, 7, 8, 13, 14, 15, 16, 17, 18}

def render(descriptor_path):
    raw = descriptor_path.read_bytes()
    files = descriptor_pb2.FileDescriptorSet.FromString(raw)
    pool = descriptor_pool.DescriptorPool()
    pending = list(files.file)
    while pending:
        left = []
        for file in pending:
            try: pool.AddSerializedFile(file.SerializeToString())
            except (TypeError, ValueError): left.append(file)
        if len(left) == len(pending): raise ValueError('Unresolved descriptor dependencies: ' + ', '.join(f.name for f in left))
        pending = left
    schema, routes, vectors = {}, {}, []
    def visit(message, prefix):
        name = prefix + '.' + message.name
        resolved = pool.FindMessageTypeByName(name)
        fields = []
        for field in message.field:
            actual = resolved.fields_by_number[field.number]
            packed = actual.is_packed
            fields.append(dict(name=field.name, number=field.number, type=field.type,
                typeName=field.type_name.lstrip('.'), repeated=field.label == 3,
                packed=packed, presence=actual.has_presence, required=actual.is_required, oneof=field.oneof_index if field.HasField('oneof_index') else None))
            if field.label == 3 and field.type in PACKABLE:
                instance = message_factory.GetMessageClass(resolved)()
                if field.type == 8: values = [False, True]
                elif field.type in (1, 2): values = [0.0, 1.25]
                elif field.type == 14: values = [v.number for v in actual.enum_type.values[:2]]
                else: values = [0, 1, 2, 127, 128]
                getattr(instance, field.name).extend(values)
                php_values = values if field.type in (1, 2, 8) else [str(v) for v in values]
                vectors.append(dict(type=name, field=field.name, packed=packed,
                    values=php_values, protobuf_hex=instance.SerializeToString(deterministic=True).hex()))
        schema[name] = dict(fields=fields)
        for nested in message.nested_type: visit(nested, name)
    for file in files.file:
        for message in file.message_type: visit(message, file.package)
        for service in file.service:
            for method in service.method:
                if service.name == 'Query' or (file.package == 'cosmos.tx.v1beta1' and service.name == 'Service' and method.name in ['Simulate', 'GetTx', 'GetTxsEvent', 'GetBlockWithTxs']):
                    routes[file.package + '.' + service.name + '/' + method.name] = dict(input=method.input_type.lstrip('.'), output=method.output_type.lstrip('.'))
    return {'schema.json': schema, 'grpc-routes.json': routes,
        'packed-vectors.json': vectors,
        'descriptor-import.json': {'descriptor_sha256': hashlib.sha256(raw).hexdigest(), 'message_count': len(schema), 'packed_field_count': sum(f['packed'] for m in schema.values() for f in m['fields']), 'source': 'Pinned Go SDK decimal.pb descriptor set'}}

def main():
    parser = argparse.ArgumentParser(__doc__)
    parser.add_argument('descriptor', type=Path)
    parser.add_argument('--check', action='store_true')
    args = parser.parse_args()
    for name, data in render(args.descriptor).items():
        path = ROOT / 'resources' / 'cosmos' / name
        content = (json.dumps(data, indent=2) + '\n').encode()
        if args.check:
            if not path.exists() or path.read_bytes() != content: raise SystemExit('Stale Cosmos resource: ' + name)
        else: path.write_bytes(content)
    print('Cosmos descriptor resources ' + ('verified' if args.check else 'imported'))

if __name__ == '__main__': main()
