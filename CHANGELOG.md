# Changelog

## 0.1.0-alpha.1 - 2026-10-29

Initial public preview of the independent Decimal PHP SDK.

- Import versioned node/Go/Python ABI profiles and generate 577 bindings.
- Add strict ABI and canonical RLP codecs, exact units, chain checks and fee preparation.
- Add BIP39/BIP32 wallets, EIP-155/2930/1559, EIP-712, permit and Safe EOA signing.
- Add allowance plans, DEL multisend, event decoding and runtime/proxy verification.
- Add REST reads, Bech32 conversion and experimental WebSocket subscriptions.
- Import Cosmos schemas and implement offline direct signing verified against Go bytes.
- Remove the dependency chain containing phpseclib and its Composer advisory.
- Include independent encoding/signature fixtures, source evidence and CI.

This is a prerelease. Complete workflow, policy and Cosmos validation parity remains work in progress; see docs/PARITY.md. No blockchain broadcasts or funded-wallet acceptance tests were performed.
