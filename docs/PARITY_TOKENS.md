# Token operation parity

`parity-tokens.json` records exact Python/C# snapshot hashes and public method declarations. The Go generated Token/TokenCenter signatures are retained in the packaged operation catalog. SDK methods create drafts/plans; the common execution layer prepares/signs and parses receipts. No transactions were submitted.

| Reference operation | PHP equivalent |
| --- | --- |
| approve/send/transferFrom ERC20 | Erc20Service approve/transfer/transferFrom; raw C# builders transferRaw/transferFromRaw |
| token buy/sell | TokenEconomy buy/sell, exact unit conversion, recipient and slippage floors |
| convert | TokenEconomy convert, different-token validation, balance/allowance reads, explicit approval/reset plan or checked EIP2612 permit |
| create token | TokenEconomy create, exact tuple ordering, explicit creator/reserve, Python symbol-length commission plus minimum reserve default |
| mint/burn | TokenEconomy human-unit adapters, existing TokenService cap/balance checks and role/flag simulation |
| reserveless creation | TokenEconomy createReserveless, exact mintable/burnable/supply/cap/identity flags |
| details/maintenance | TokenEconomy updateDetails and maintain with explicit observed reservable profile |
| creation receipt / symbol resolution | TokenEconomy createdToken and tokenAddressBySymbol; Events receipt remains available for multiple events |
| reserve state / quote overloads | TokenReader state/read/quote; all four quote directions, current or custom supply/reserve/CRR |
| C# exact-input/exact-output trade aliases | TokenEconomy tradeRaw with all four source names and exact native value/cap ordering |
| ERC20 metadata/domain/nonce | Erc20Service info/read and existing TypedData checkedPermit / permit / hash |

All amounts remain decimal strings. Human-unit methods resolve decimals only when omitted; raw builders remain available without balance preflight. State reports pin latest to a concrete block. Approvals are separate drafts and must be confirmed before rebuilding/simulating an action; no automatic broadcast is enabled. Token-created receipt matching rejects zero, absent and ambiguous addresses. No unavailable token information is silently fabricated.

Tests include independent eth_abi/eth_utils calldata fixtures, exact creation fee schedule, oversupply encoding limits, balance/allowance failures, disabled automatic approval, reset ordering, fractional precision failures, all quote overloads and explicit maintenance profile rejection. These checks establish source-level behavior, not acceptance against mutable live balances/roles.

PermitService prepares canonical external-signer Permit data, verifies on-chain domain and chain, classifies absent optional version getters narrowly, and recovers low-s signatures against the requested owner. Provider errors propagate. Tests cover missing getters, nonce binding, future deadlines, altered payloads and mismatched domains.
