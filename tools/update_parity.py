"""Reconcile the audited operation catalog with source-backed service manifests."""
import argparse
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def generate():
    path = ROOT / 'docs/audit-operation-parity.json'
    audit = json.loads(path.read_text(encoding='utf-8'))
    groups = {
        'staking': ['tests/staking-parity.php', 'tests/staking-reads-parity.php'],
        'tokens': ['tests/token-parity.php'],
        'nft': ['tests/nft-parity.php'],
    }
    helpers = {}
    evidence = {}
    for group, tests in groups.items():
        manifest = f'docs/parity-{group}.json'
        operations = json.loads((ROOT / manifest).read_text(encoding='utf-8'))['operations']
        if isinstance(operations, list):
            operations = {entry['operation']: entry['php_helper'] for entry in operations}
        for operation, helper in operations.items():
            helpers[operation] = [helper]
            evidence[operation] = dict(manifest=manifest, tests=tests)
    common = {
        'tx.send_del': ['TransactionDraft::del'],
        'nft.transfer': ['NftService::transfer721', 'NftService::transfer1155', 'NftOperations::transferFrom'],
        'checks.create_del': ['ChecksService::createDelForSigners', 'ChecksService::createDel'],
        'checks.create_token': ['ChecksService::createTokenForSigners', 'ChecksService::createToken'],
        'checks.redeem': ['ChecksService::redeemSignatures', 'ChecksService::redeem'],
        'bridge.transfer_native': ['BridgeService::transferNative'],
        'bridge.transfer_token': ['BridgeService::transferToken'],
        'bridge.complete_transfer': ['BridgeService::completeTransfer'],
    }
    for operation, methods in common.items():
        helpers[operation] = methods
        evidence[operation] = dict(manifest='docs/PARITY_COMMON.md', tests=['tests/bridge-checks-parity.php', 'tests/executor-parity.php'] if operation != 'nft.transfer' else ['tests/nft-parity.php'])
    names = [row['python_operation'] for row in audit['operations']]
    if len(names) != 55 or len(set(names)) != 55 or set(names) != set(helpers):
        raise ValueError(f'Operation inventory differs: missing={set(names)-set(helpers)}, extra={set(helpers)-set(names)}')
    audit['reference_note'] = 'Pinned source snapshots and exact request/draft/read/report semantics. Common TransactionExecutor supplies prepare/sign/explicit-broadcast/receipt orchestration. Funded node admission is outside offline acceptance. Unsafe generic ERC20 Multicall allowance remains intentionally disabled, with a verified RestrictedMultisend alternative.'
    audit['release'] = (ROOT / 'VERSION').read_text(encoding='ascii').strip()
    for row in audit['operations']:
        operation = row['python_operation']
        row['status'] = 'implemented-workflow'
        row['php_helpers'] = helpers[operation]
        row['implementation_evidence'] = evidence[operation]
        row['semantic_boundary'] = 'Explicit deployment/profile and current state remain authoritative; amounts are lossless strings. TransactionExecutor prepares/signs and reports the action; approval plans require confirmed prerequisites and explicit state refresh.'
        if operation == 'decimal.multisend_erc20':
            row['status'] = 'disabled-unsafe-reference'
            row['php_helpers'] += ['RestrictedMultisend::plan', 'RestrictedMultisend::withPermit']
            row['semantic_boundary'] = 'Arbitrary Multicall ERC20 allowance is disabled, matching the maintained Python and Go safety policy. Recovery Python differs here; its unsafe spender path is not copied. An explicitly verified sender-restricted multisend is supported separately.'
    lines = [
        '# Transaction operation parity', '',
        'All 55 catalog entries now have named request-level implementations or an explicit source-matched safety rejection. The table records 54 implemented workflows and one intentionally disabled generic Multicall ERC20 spender path. This is a source/API acceptance statement, not funded-network acceptance.', '',
        'Each implementation is backed by independent calldata/digest fixtures, injected state and negative checks. Common TransactionPlan/TransactionExecutor handles prepare/sign/report and explicit broadcast/receipt processing. Approval prerequisites are confirmed before a state refresh and dependent action preparation. No blockchain transactions were submitted.', '',
        '| Python operation | State | PHP API | Evidence |', '| --- | --- | --- | --- |',
    ]
    for row in audit['operations']:
        api = ', '.join(f'`{method}`' for method in row['php_helpers'])
        manifest = row['implementation_evidence']['manifest']
        lines.append(f"| `{row['python_operation']}` | {row['status']} | {api} | [{Path(manifest).stem}]({Path(manifest).name}) |")
    lines += ['', 'Detailed C#/Go read, permit, quote, management, receipt and DTO correspondence is recorded in [staking](PARITY_STAKING.md), [tokens](PARITY_TOKENS.md), [NFT](PARITY_NFT.md) and [common orchestration](PARITY_COMMON.md). Source hashes and original request locations remain in the JSON companion and linked manifests.', '',
              'The upgraded mainnet stake format, original legacy exits and separate unified NFTStake tuple10 remain supported. Unsupported deployment ABIs, stale nonces/domains, insufficient balances, failed approvals, malformed known events and unavailable reads fail explicitly. Receipt availability and successful simulation do not establish cross-chain settlement or funded NFT lifecycle acceptance.', '']
    return {path: json.dumps(audit, indent=2, ensure_ascii=False) + '\n', ROOT / 'docs/AUDIT_OPERATION_PARITY.md': '\n'.join(lines)}


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--check', action='store_true')
    args = parser.parse_args()
    for path, content in generate().items():
        if args.check:
            if path.read_text(encoding='utf-8') != content:
                raise SystemExit(f'Stale parity catalog: {path.name}')
        else:
            path.write_text(content, encoding='utf-8', newline='\n')
    print('55 operation mappings verified: 54 implemented, 1 explicitly disabled unsafe reference')
