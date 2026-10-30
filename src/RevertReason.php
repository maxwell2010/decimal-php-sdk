<?php
declare(strict_types=1);
namespace Decimal;

/** Decodes returned bytes, without asserting which contract originated them. */
final class RevertReason
{
    private const PANICS = [
        '0'=>'Generic compiler panic', '1'=>'Assertion failed',
        '17'=>'Arithmetic overflow or underflow', '18'=>'Division or modulo by zero',
        '33'=>'Invalid enum conversion', '34'=>'Invalid storage byte array encoding',
        '49'=>'Pop on an empty array', '50'=>'Array index out of bounds',
        '65'=>'Memory allocation overflow', '81'=>'Uninitialized internal function',
    ];

    /** Custom errors require the caller's explicit ABI, never a selector database guess. */
    public static function decode(string $data, array $errorAbi = []): array
    {
        if (!preg_match('/^0x(?:[a-fA-F0-9]{2})*$/D', $data)) throw new \InvalidArgumentException('Invalid revert bytes');
        if (!array_is_list($errorAbi)) throw new \InvalidArgumentException('Error ABI must be a list');
        foreach ($errorAbi as $entry) {
            if (!is_array($entry)) throw new \InvalidArgumentException('Invalid error ABI entry');
            if (($entry['type']??null)!=='error') continue;
            if (!is_string($entry['name']??null) || !preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/D',$entry['name']) || !is_array($entry['inputs']??null) || !array_is_list($entry['inputs'])) throw new \InvalidArgumentException('Invalid error ABI entry');
            foreach ($entry['inputs'] as $input) self::validateInput($input);
        }
        $raw = strtolower($data);
        $result = ['kind'=>'unknown', 'data'=>$data, 'selector'=>strlen($raw)>=10?substr($raw,0,10):null];
        if ($raw==='0x') return array_replace($result, ['kind'=>'empty']);
        if (strlen($raw)<10) return array_replace($result, ['kind'=>'malformed']);
        $codec = new AbiCodec(); $payload = '0x'.substr($raw,10);
        // Built-in Error/Panic selectors retain their standard interpretation.
        try {
            if ($result['selector']==='0x08c379a0') {
                $args = $codec->decodeParameters(['string'],$payload);
                return array_replace($result,['kind'=>'error','signature'=>'Error(string)','arguments'=>$args,'message'=>$args[0]]);
            }
            if ($result['selector']==='0x4e487b71') {
                $args = $codec->decodeParameters(['uint256'],$payload);
                return array_replace($result,['kind'=>'panic','signature'=>'Panic(uint256)','arguments'=>$args,'panic_code'=>$args[0],'message'=>self::PANICS[$args[0]]??'Unknown panic code']);
            }
        } catch (\InvalidArgumentException) {
            return array_replace($result,['kind'=>'malformed']);
        }
        $matches=[]; $matchedSelector=false;
        foreach ($errorAbi as $entry) {
            if (($entry['type']??null)!=='error') continue;
            $signature=Contract::signature($entry);
            if ($codec->encodeFunctionSignature($signature)!==$result['selector']) continue;
            $matchedSelector=true;
            try {
                $args=$codec->decodeParameters(array_map(Contract::type(...),$entry['inputs']??[]),$payload);
                $matches[$signature]=['kind'=>'custom','signature'=>$signature,'arguments'=>$args];
            } catch (\InvalidArgumentException) { /* Retain the returned bytes for a mismatched ABI. */ }
        }
        if (count($matches)===1) return array_replace($result,array_values($matches)[0]);
        if (count($matches)>1) return array_replace($result,['kind'=>'ambiguous','candidates'=>array_values($matches)]);
        return array_replace($result,['kind'=>$matchedSelector?'malformed':'unknown']);
    }

    private static function validateInput(mixed $input): void
    {
        if (!is_array($input) || !is_string($input['type']??null) || $input['type']==='') throw new \InvalidArgumentException('Invalid error ABI input');
        if (str_starts_with($input['type'],'tuple')) {
            if (!is_array($input['components']??null) || !array_is_list($input['components'])) throw new \InvalidArgumentException('Invalid error ABI tuple');
            foreach ($input['components'] as $component) self::validateInput($component);
        }
    }
}
