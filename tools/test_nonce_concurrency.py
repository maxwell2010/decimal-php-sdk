"""Cross-process nonce reservation regression, with no node or transaction transport."""
from concurrent.futures import ThreadPoolExecutor
import json
import os
from pathlib import Path
import subprocess
import tempfile
ROOT=Path(__file__).resolve().parents[1]

def main():
    cmd=[os.environ.get('PHP_BINARY','php')]
    if 'gmp' not in subprocess.check_output(cmd+['-m'],text=True).splitlines():cmd+=['-d','extension=gmp']
    with tempfile.TemporaryDirectory(prefix='decimal-nonce-') as tmp:
        store=Path(tmp)/'reservations.json'
        def worker(_):
            raw=subprocess.check_output(cmd+[str(ROOT/'tests/nonce-worker.php'),str(store),'25'],text=True,creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0))
            return json.loads(raw)
        with ThreadPoolExecutor(max_workers=4) as pool:results=list(pool.map(worker,range(4)))
        nonces=[int(n) for row in results for n in row]
        assert len(set(nonces))==100 and sorted(nonces)==list(range(100)),nonces
        assert list(json.loads(store.read_text()).values())==['100']
    print('100 cross-process nonce reservations passed; no broadcasts')

if __name__=='__main__':main()
