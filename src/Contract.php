<?php
declare(strict_types=1);
namespace Decimal;

class Contract
{
    private AbiCodec $codec;
    public function __construct(public readonly string $address, private readonly array $abi)
    { TransactionDraft::address($address); $this->codec = new AbiCodec(); }
    public static function profile(string $address, string $profile): self
    {
        if (!preg_match('/^[A-Za-z0-9]+$/D', $profile)) throw new \InvalidArgumentException('Invalid ABI profile');
        $path = dirname(__DIR__) . '/abi/' . $profile . '.json';
        if (!is_file($path)) throw new \InvalidArgumentException('Unknown ABI profile');
        return new self($address, json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR));
    }
    public static function type(array $input): string
    { return str_starts_with($input['type'], 'tuple') ? '(' . implode(',', array_map(self::type(...), $input['components'])) . ')' . substr($input['type'], 5) : $input['type']; }
    public static function signature(array $entry): string
    { return $entry['name'] . '(' . implode(',', array_map(self::type(...), $entry['inputs'] ?? [])) . ')'; }
    public function function(string $signature): array
    {
        foreach ($this->abi as $entry) if (($entry['type'] ?? '') === 'function' && self::signature($entry) === $signature) return $entry;
        throw new \InvalidArgumentException('Unknown function signature: ' . $signature);
    }
    public function encode(string $signature, array $args = []): string
    {
        $entry = $this->function($signature);
        if (count($args) !== count($entry['inputs'])) throw new \InvalidArgumentException('Argument count mismatch');
        foreach ($entry['inputs'] as $i => $input) self::validate($input, $args[$i]);
        return $this->codec->encodeFunctionSignature($signature) . substr($this->codec->encodeParameters(array_map(self::type(...), $entry['inputs']), $args), 2);
    }
    private static function validate(array $input, mixed $value): void
    {
        $type = $input['type'];
        if (preg_match('/^(.*)\[([0-9]*)\]$/D', $type, $m)) {
            if (!is_array($value) || !array_is_list($value) || ($m[2] !== '' && count($value) !== (int)$m[2])) throw new \InvalidArgumentException('Invalid ABI array');
            $input['type'] = $m[1]; foreach ($value as $v) self::validate($input, $v); return;
        }
        if ($type === 'tuple') {
            if (!is_array($value) || !array_is_list($value) || count($value) !== count($input['components'])) throw new \InvalidArgumentException('Invalid tuple');
            foreach ($input['components'] as $i => $child) self::validate($child, $value[$i]); return;
        }
        if (preg_match('/^(u?int)([0-9]*)$/D', $type, $m)) {
            $bits = (int)($m[2] ?: 256);
            if (!is_string($value) || !preg_match('/^-?(0|[1-9][0-9]*)$/D', $value)) throw new \InvalidArgumentException('ABI integers require decimal strings');
            $signed = $m[1] === 'int'; $limit = bcpow('2', (string)($bits - ($signed ? 1 : 0)), 0);
            if (bccomp($value, $signed ? bcsub('0', $limit, 0) : '0', 0) < 0 || bccomp($value, $limit, 0) >= 0) throw new \InvalidArgumentException('ABI integer overflow');
        } elseif ($type === 'address') { if (!is_string($value)) throw new \InvalidArgumentException('Invalid address'); self::addressValue($value); }
        elseif ($type === 'bool' && !is_bool($value)) throw new \InvalidArgumentException('Expected bool');
        elseif ($type === 'string' && !is_string($value)) throw new \InvalidArgumentException('Expected string');
        elseif (preg_match('/^bytes([0-9]*)$/D', $type, $m)) {
            if (!is_string($value) || !preg_match('/^0x(?:[0-9a-fA-F]{2})*$/D', $value) || ($m[1] !== '' && strlen($value) !== 2 + 2*(int)$m[1])) throw new \InvalidArgumentException('Invalid ABI bytes');
        }
    }
    private static function addressValue(string $value): void { TransactionDraft::address($value); }
    public function decode(string $signature, string $data): array
    { return $this->codec->decodeParameters(array_map(self::type(...), $this->function($signature)['outputs']), $data); }
    public function draft(string $from, string $signature, array $args = [], string $value = '0'): TransactionDraft
    {
        $entry = $this->function($signature);
        if (in_array($entry['stateMutability'], ['view','pure'], true)) throw new \InvalidArgumentException('Use call for read functions');
        if ($value !== '0' && $entry['stateMutability'] !== 'payable') throw new \InvalidArgumentException('Function is not payable');
        return new TransactionDraft($from, $this->address, $value, $this->encode($signature, $args));
    }
    public function call(Client $client, string $signature, array $args = [], string $block = 'latest'): array
    { return $this->decode($signature, $client->call(['to'=>$this->address, 'data'=>$this->encode($signature, $args)], $block)); }
}
