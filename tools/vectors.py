"""Reference calldata generated independently with Python eth-abi and eth-utils."""
import json
import re
from eth_abi import encode
from eth_utils import keccak
from generate import ROOT, abi_type

def value(item):
    t = item['type']
    m = re.fullmatch(r'(.*)\[([0-9]*)\]', t)
    if m:
        return [value(dict(item, type=m[1])) for _ in range(int(m[2] or 2))]
    if t == 'tuple': return [value(i) for i in item['components']]
    if t.startswith(('uint', 'int')): return 123
    if t == 'address': return '0x' + '12' * 20
    if t == 'bool': return True
    if t == 'string': return 'Decimal test'
    if t.startswith('bytes'): return b'\xab' * int(t[5:] or 3)
    raise ValueError(t)

def php_value(v):
    if isinstance(v, bool): return v
    if isinstance(v, int): return str(v)
    if isinstance(v, bytes): return '0x' + v.hex()
    if isinstance(v, list): return list(map(php_value, v))
    return v

rows = []
for path in sorted((ROOT / 'abi').glob('*.json')):
    for e in json.loads(path.read_text()):
        if e.get('type') != 'function': continue
        types = list(map(abi_type, e['inputs']))
        sig = e['name'] + '(' + ','.join(types) + ')'
        args = list(map(value, e['inputs']))
        rows.append(dict(profile=path.stem, signature=sig, args=php_value(args), data='0x' + (keccak(text=sig)[:4] + encode(types, args)).hex()))
(ROOT / 'tests' / 'vectors.json').write_text(json.dumps(rows, indent=2) + '\n', newline='\n')
print(len(rows), 'independent calldata vectors')
