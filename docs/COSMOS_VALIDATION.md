# Cosmos validation and transport scope

The Cosmos surface is split into explicit levels of assurance. No helper in this module broadcasts transactions.

- `Protobuf::encode` and `decode` use the 393 pinned message descriptors. Decode rejects unknown fields, duplicate singular fields, conflicting oneofs, overflowing/noncanonical varints, invalid wire types, malformed UTF-8 and truncated payloads. Nesting and message size are bounded. This is deliberately stricter than generic protobuf parsing, which can preserve unknown fields or merge duplicates.
- `Cosmos::sign` is the compatibility-level direct signer. It accepts caller-specified signer order and reproduces the Go reference fixture, including the 65-byte Ethermint signature convention. It does not validate message signers or economics.
- `Cosmos::requiredSigners` follows the 43 individually recorded GetSigners rules from the pinned Go/node reference. It decodes message payloads and preserves first message occurrence; an optional fee-payer argument appends a previously unseen payer. It does not infer roles from field names or sort signers by address.
- `Cosmos::signChecked` binds every ordered signer wallet to the corresponding source-backed message address before signing. It rejects unknown message types, malformed addresses, signer-count/order mismatches and duplicate signer keys. It does not itself run ValidateBasic; use signValidated when stateless policy validation is required.
- `Cosmos::signValidated` additionally applies all 43 supported pinned ValidateBasic policies before signing. Unknown policies fail closed. It does not fall back to schema-only acceptance.

## ValidateBasic policy coverage

`CosmosValidation::supported()` returns all 43 transaction type URLs in the pinned inventory. Every type has an explicit source-backed branch, covering coin creation/update/send/conversion/redeem, NFT mint/reserve/send/burn, validator creation/edit/staking/cancellation/NFT/chain controls, legacy return, oracle prices, multisig, swaps and upgrade/cancel-upgrade.

The port preserves source quirks rather than silently adding stricter rules: coin UpdateCoin limit-volume comparison; raw integer representations of SDK decimals; negative-but-nonzero NFT reserve updates; absence of an invented multisig minimum threshold; RedeemCheck checks omitted by upstream; ungrouped alternation in the application's SDK denomination regex, including its recorded whitelist; and upgrade timestamp sentinel checks. Unknown types fail closed. Schema-level malformed values fail rather than reproducing node nil/panic behavior.

`resources/cosmos/validation-subset.json` now contains the complete 129-case actual-node corpus (43 valid messages, 43 empty messages and 43 invalid-signer messages). Acceptance is compared to actual node `valid`, not merely another port. Eleven additional reference boundary fixtures and explicit negative amount, denomination, overflow and self-transfer tests extend coverage. The original name of the corpus file is retained for compatibility with the release tests.

This covers the pinned stateless source policy surface, not every possible value combination or a security audit. Validation does not establish governance authority, validator/oracle membership, account balance, sequence, active route admission, price freshness, network expiry or successful node ante/execution acceptance. No remote node mutation was used in testing.

## Address compatibility

`Address::bech32Bytes` and `fromBech32Bytes` implement the pinned Cosmos 1..255-byte address boundary and expected HRP checks. Existing `bech32`/`fromBech32` retain the explicit 20-byte EVM-address conversion contract. No legacy public-key hash is inferred. Operator addresses retain their original byte payload when the expected HRP changes.

## Queries and simulation

`CosmosGrpc` implements unary gRPC framing over HTTPS HTTP/2 using PHP curl, with bounded messages and timeouts, no redirects/retries, required grpc-status success and strict response decoding. Its 56 allowed routes are generated from the pinned descriptor set: Query services plus explicitly read/simulation-only Cosmos transaction service methods. Msg services and BroadcastTx are rejected before transport. `simulate` submits only a SimulateRequest. Applications must provide an actual HTTPS gRPC endpoint; a REST gateway URL is not interchangeable.

Tests inject framed wire responses, verify request protobuf payloads, and reject mutation routes, errors and invalid frames. A live-provider gRPC compatibility campaign has not been run.

`CosmosFees::converge` implements the Go SDK callback-based fixed-point algorithm: rebuild and sign at fee F, quote the exact signed bytes and byte length under a pinned parameter snapshot, then repeat until the quote equals F. It rejects cycles, stale snapshots, oversized transactions, uint256/cap violations and exhausted iterations. The callbacks must keep transaction messages, chain, sequences, account numbers and signer order fixed. This helper does not invent commission rates, query prices itself or broadcast.

The source schemas, source inventories and signing fixture already bundled in `resources/cosmos` identify the original pinned reference. The added signer-rule and gRPC-route maps are generated directly from that reference's `signers.go` and `decimal.pb`, respectively.

## Descriptor import and packed repeated fields

`python tools/import_cosmos.py path/to/decimal.pb` regenerates schema, read-only gRPC routes and independent repeated-field wire fixtures from the original descriptor set. `--check` verifies reproducibility without writing. This development tool requires Python protobuf; the PHP runtime needs only the generated JSON files. The descriptor SHA256 is recorded in `resources/cosmos/descriptor-import.json`.

Packing is resolved using the protobuf descriptor's `is_packed` property, preserving explicit options and proto3 default packing. Reading only `FieldOptions.packed` loses the proto3 default and changes transaction bytes and length-dependent fee inputs. The importer identifies 17 packed fields among 19 repeated packable fields. Independent Python protobuf fixtures test all 19, including explicitly nonpacked/proto2 fields. A dedicated multisig regression asserts `weights=[1,2]` produces `1a020102`.

## Signed envelope validation

`signDoc` strictly decodes both TxBody and AuthInfo before producing sign bytes. The compatibility-level `sign` method calls that validation before any signature and assembles its result through `txRaw`. `txRaw` validates body/auth wire shape, nonempty signatures, signer/signature count equality and 65-byte Ethermint signatures with recovery byte 0 or 1. `validateTxRaw` additionally requires at least one message and is intended as a transport boundary check. These checks do not prove cryptographic authorization or business execution.

Low-level AuthInfo fee encoding follows the pinned Go codec: denomination must be nonempty and amount must contain decimal digits; zero and leading zeros are representable at this codec level. Economic/ante fee admissibility remains a node concern. Tests cover malformed body/auth, missing and excessive signatures, wrong length/recovery byte, empty signed bodies, and the unchanged reference TxRaw bytes.

Native gRPC additionally checks curl HTTP/2 capability before connecting and rejects any negotiated protocol other than HTTP/2. The grpc-status parser anchors the complete header, rejects malformed values such as `0garbage`, rejects duplicate statuses, and does not treat HTTP/1 fallback as gRPC success. Pure parser/protocol tests cover these rejection paths without contacting a server.
