<?php
declare(strict_types=1);
namespace Decimal;

final readonly class TransactionDraft
{
    public function __construct(public string $from, public ?string $to, public string $value = '0', public string $data = '0x')
    {
        self::address($from); if($to!==null) self::address($to); Units::uint($value);
        if (!preg_match('/^0x(?:[a-fA-F0-9]{2})*$/D', $data)) throw new \InvalidArgumentException('Invalid transaction bytes');
    }
    public static function address(string $value): string
    {
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/D', $value)) throw new \InvalidArgumentException('Invalid EVM address');
        return strtolower($value);
    }
    public static function del(string $from, string $to, string $amount, string $memo = ''): self
    { return new self($from, $to, Units::parse($amount), '0x' . bin2hex($memo)); }
    public static function deploy(string $from,string $initCode,string $value='0'): self
    { return new self($from,null,$value,$initCode); }
    public function rpc(): array
    {
        $request=['from'=>$this->from, 'value'=>Units::hex($this->value), 'data'=>$this->data];
        if($this->to!==null) $request['to']=$this->to;
        return $request;
    }
}
