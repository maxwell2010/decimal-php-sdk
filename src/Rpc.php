<?php
declare(strict_types=1);
namespace Decimal;

final class Rpc
{
    private int $id = 0;
    public function __construct(private readonly string $url, private readonly ?\Closure $transport = null) {}
    public function call(string $method, array $params = []): mixed
    {
        $request = ['jsonrpc'=>'2.0', 'id'=>++$this->id, 'method'=>$method, 'params'=>$params];
        if ($this->transport) $response = ($this->transport)($request);
        else {
            $curl = curl_init($this->url);
            curl_setopt_array($curl, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($request, JSON_THROW_ON_ERROR), CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>30]);
            $raw = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $failed = $raw === false; curl_close($curl);
            if ($failed || $status !== 200) throw new RpcException('RPC transport failed', (int)$status);
            try { $response = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
            catch (\JsonException $e) { throw new RpcException('Invalid RPC JSON'); }
        }
        if (!is_array($response) || ($response['jsonrpc'] ?? null) !== '2.0' || ($response['id'] ?? null) !== $request['id']) throw new RpcException('Invalid RPC envelope');
        if (isset($response['error'])) throw new RpcException((string)($response['error']['message'] ?? 'RPC error'), (int)($response['error']['code'] ?? 0), $response['error']['data'] ?? null);
        if (!array_key_exists('result', $response)) throw new RpcException('Missing RPC result');
        return $response['result'];
    }
}
