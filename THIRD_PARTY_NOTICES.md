# Third-party source notices

ABI snapshots and protobuf descriptors were imported from the existing Go and Python SDK references. Original node and JS license texts are included as THIRD_PARTY_NODE_LICENSE and THIRD_PARTY_JS_LICENSE. Source repositories, commits and hashes are recorded under docs and resources/cosmos.

The English BIP39 wordlist is from https://github.com/bitcoin/bips/blob/master/bip-0039/english.txt. The specification and source are documented at https://github.com/bitcoin/bips/blob/master/bip-0039.mediawiki.

Runtime dependencies are installed separately by Composer; their license metadata and exact versions are recorded in composer.lock. Vendor code is not bundled in the source release ZIP.

SDK-owned ABI/RLP/protobuf codecs were written for this package. Calldata and cryptographic test vectors use Python eth-abi/eth-account as independent references. Cosmos direct-signing fixtures and source inventories come from the local Go SDK, as recorded in resources/cosmos.
