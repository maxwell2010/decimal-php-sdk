# Cosmos final audit for 0.1.1

Audited the public PHP 0.1.0 sources against the current read-only Go Cosmos codec, signing and validation references in gosdk/cosmos. No blockchain transaction or remote simulation was submitted.

## Confirmed corrections

- Protobuf decoding already bounded nesting to 64 and wire messages to 8 MiB, but encoding had neither corresponding bound. Recursive DescriptorProto inputs, including cyclic PHP references, could recurse without termination. Encoding now checks depth, rejects negative caller-supplied depth, and bounds string/bytes, packed buffers and total output. A shallow recursive descriptor still round-trips; depth-66, cyclic and oversized inputs fail. This is a resource-safety correction, not an invented node business predicate.
- Cosmos signDoc arbitrarily rejected chain IDs longer than 128 bytes, while the pinned Go EncodeSignDoc only requires a nonempty chain string. Removed that invented limit. The exact protobuf bytes for a 129-byte chain domain are asserted, while normal codec message-size and UTF-8 checks still apply.
- Signer APIs now explicitly require positional lists. Previously auth accepted associative signer maps and signChecked could index the required signer list using caller-provided map keys. Ordered arrays are now checked before constructing or signing the envelope.

## Evidence and regression integration

Include tests/audit-cosmos-0-1-1.php in the existing tests/run.php harness. It adds 20 focused assertions including the eleven independently recorded actual-node upgrade boundary cases. The existing full 43-type ValidateBasic corpus, packed-field protobuf vectors and Go direct-signing fixture remain separate regression evidence.

The Go EncodeAuthInfo low-level codec accepts representable public-key bytes, rather than proving curve membership, while Go direct signing validates actual signer keys. PHP signing likewise obtains keys from validated Wallet instances. No additional low-level curve-policy change was labeled as a parity fix.

## Remaining boundaries

- Cosmos stateless validation is not account-role, governance, oracle, ante or execution acceptance.
- gRPC mutation routes remain rejected. Native HTTP/2 transport is locally/wire tested; no live-provider mutation or funded acceptance test is claimed.
- Fee convergence remains a callback protocol requiring a fixed transaction identity and pinned oracle/parameter snapshot; it does not guess network rates.
- Generic protobuf decoding intentionally rejects unknown/duplicate fields instead of silently discarding data. That strict versioned behavior is distinct from every permissive upstream protobuf decoder case.
## Additional independent descriptor audit

A new development-time generator, tools/cosmos_differential_vectors.py, loads the original pinned FileDescriptorSet through the official Python protobuf runtime and emits 2119 independent field-wire vectors across all 393 message descriptors. It covers nonzero fields, explicitly supplied scalar defaults, empty-message presence, repeated/packed values and individual oneof fields. The pinned set contains no map-entry descriptors. Proto2 descriptor messages with required fields use SerializePartialToString to test individual field wire representation; these partial fixture messages are not claims of complete application-message validity.

This uncovered 74 encode mismatches: the initial schema export retained oneof/packed metadata but omitted ordinary proto2 field presence. For example, explicitly supplying DescriptorProto.name = empty string must encode 0a00, whereas omission encodes no bytes. PHP previously collapsed those two cases. The importer now records resolved FieldDescriptor.has_presence, and the encoder only elides explicitly supplied defaults on fields that do not have presence. All initialized independent messages now byte-match and preserve decode/re-encode wire bytes; incomplete proto2 required-field fixtures are explicitly rejected, as clarified below.

Include tests/audit-cosmos-differential.php in the shared harness for 4239 checks. The fixture records the SHA256 of the exact original descriptor bytes, and both importer and vector generator accept an explicit descriptor filename rather than a machine-specific runtime path.

The fee convergence audit confirmed source-equivalent boundaries: fee values/snapshot are immutable PHP strings, quote/build callbacks receive copied values, caps and cycles are checked, and returned bytes are those built at the converged fee. As in Go ConvergeFee, message/chain/account/sequence identity across arbitrary callbacks is an explicit application requirement, not something a generic signed-byte callback can infer. No stronger identity guarantee was invented.

The checked/validated signing flow derives signers from pinned per-message GetSigners rules, preserves first occurrence, binds each Wallet address before signing, and validates messages using the stateless policy port. No additional signer-order bypass was found beyond the non-list-input correction above. Account/module roles and ante rules remain node-authoritative.
## Required-field initialization correction

The follow-up audit also distinguishes wire representation from complete proto2 initialization. The importer now records resolved is_required metadata. Encode and decode reject absent required fields, matching official protobuf initialization behavior. Each independent vector now records Python IsInitialized(); complete vectors must match encoded/decoded bytes, while partial descriptor fixtures must be rejected. This closes the NamePart required-field edge case rather than presenting partial messages as accepted complete descriptors. The differential test count remains 4239, and Cosmos transaction/query wire bytes are unaffected.
