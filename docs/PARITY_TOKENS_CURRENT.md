# Current token profile delta

CurrentTokenEconomy explicitly selects the latest recovery-014 Python behavior, preserving older TokenEconomy entrypoints for callers targeting the original source profiles. Hashes are recorded in parity-tokens-current.json. No blockchain transactions were sent.

- buy/sell use buyTokenForExactDEL / sellExactTokensForDEL. Existing tradeRaw covers exact-output counterparts.
- convert defaults to no automatic approval and accepts the existing named options for decimals, permit and approval reset.
- Current testnet creation requires 3-10 lowercase ASCII letters/digits. Reserve defaults to 1 DEL plus the testnet symbol-length commission (2500/250/25/2.5/0.25 DEL), and explicitly supplied reserve cannot be below this floor. Mainnet retains the original commission schedule.
- updateDetails returns two ordered drafts, identity then maximum supply, after MIN_RESERVE read verification. Execute both sequentially with successful receipts and combine result fees explicitly; the method never returns them as approvals.
- Mint checks mintable before the existing cap/balance/role/simulation workflow.
- GasCenter convertToDel accepts explicit owner/token/amount/estimatedGas/permit. It uses the pinned gas_center ABI, not an ordinary token sell.
- updateMinSupply requires explicit legacy opt-in.
- properties returns kind, identity, creator/owner, raw/formatted supplies/cap and unlimited-cap/mintable/burnable flags at one concrete block. The kind is explicitly supplied instead of interpreting an RPC failure as evidence of another contract family.

No transaction request fields were removed from the baseline. Erc20 permit from mnemonic is supported by constructing Wallet from Mnemonic and using the existing prepared typed data/signature verification. Native burn and transaction refresh/actual-fee changes are handled by the common transaction layer.

Tests verify independent GasCenter calldata, distinct exact-trade selectors, current testnet fees/symbol/reserve guards, disabled automatic approvals, legacy minimum-supply opt-in and two-step update ordering.
