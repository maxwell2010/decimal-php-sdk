# Transport audit for 0.1.1

Audited Rpc, Api, Indexer and WebSocket against the local Go API/indexer and Python rest.py/ws.py references. No remote Decimal requests or blockchain submissions were performed.

## Confirmed fixes

- WebSocket cached arbitrary unmatched response IDs. A peer could send id 2 while subscribe id 1 was pending, then have that stale response accepted for the next request. The synchronous API now rejects mismatched IDs and closes the connection. Exactly one result/error branch and valid error metadata are required.
- Notifications now require subscription/result fields. Pending notifications are bounded both by 1000 entries and 8 MiB of encoded payload, rather than allowing 1000 separate 4 MiB messages to accumulate.
- Socket timeouts previously reset for each partial read/ping, permitting a peer to keep a request pending indefinitely. Handshake and response/frame assembly now share absolute operation deadlines. The ping-stream regression confirms that activity does not extend the request timeout.
- Subscription options must be a positional list before a request is written.
- Indexer::coin now supports slash-separated denominations such as ibc/ABC123, matching Python's explicit route, while rejecting traversal per segment. Added source-backed coinRegistry, validators, walletStakes/walletUnstakes, and explicitly configured root health/staking index read helpers. Root and index URLs are never guessed from the ordinary API base.

## Regression evidence

- tests/transport_audit.php: 10 route/configuration assertions using injected GET responses.
- tools/test_websocket_audit.py and tests/websocket-audit.php: loopback peer sends future IDs, conflicting result/error, null error, malformed notifications, oversized queues and indefinite pings; all are rejected. Invalid non-positional options never write a request.
- Existing local transport, notification queue, and failed-reconnect preservation loopback tests still pass.

## Remaining boundaries

This blocking WebSocket API does not advertise concurrent outstanding requests. Reconnection is explicit, returns an ID remap, and preserves definitions after failure. APIs preserve raw JSON shapes; a full Python wallet-summary/report DTO is not falsely claimed by these route helpers. Rpc read-only failover continues to chain-probe endpoints and never retries an ambiguous write. The latest Composer metadata uses immutable versioned archive URLs and hashes; release publication remains gated by the main workflow.
