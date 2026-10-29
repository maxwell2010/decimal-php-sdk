# Security

This SDK is an independent preview and has not received an external security audit. Review docs/PARITY.md before using it with funds. A successful unit test or Composer audit is not proof that a deployed contract or business workflow is safe.

Never commit or log live mnemonics, private keys, raw signatures or authorization material. Examples/tests use public test vectors only. Keep mainnet and testnet credentials separate and coordinate nonce allocation across applications.

Only the explicit Client::broadcast method submits an EVM transaction. Preparation and read helpers do not submit; wallet operations sign locally. Do not retry ambiguous submissions without reconciling the transaction hash and account nonce.

Verify network, ABI, runtime code and proxy implementation with trusted evidence. Builders accept explicit contract addresses and do not establish deployment trust. Cosmos schema encoding does not reproduce upstream business validation or ante admission.

Report vulnerabilities privately through the maintainer's contact at https://mintcandy.ru/ rather than posting secrets in public GitHub issues. Do not attach production keys or phrases to any report.
