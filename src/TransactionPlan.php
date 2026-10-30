<?php
declare(strict_types=1);
namespace Decimal;

/** Approvals are prerequisites, not calls to batch with an unverified dependent action. */
final readonly class TransactionPlan
{
    public function __construct(public TransactionDraft $action, public array $approvals=[], public ?string $operation=null, public array $metadata=[])
    {
        if(!array_is_list($approvals)||count($approvals)>500)throw new \InvalidArgumentException('Positional approval list required');
        foreach($approvals as $step)if(!$step instanceof TransactionDraft||TransactionDraft::address($step->from)!==TransactionDraft::address($action->from))throw new \InvalidArgumentException('Every plan step must have the same sender');
    }
    public static function from(TransactionDraft|array|self $plan): self
    {
        if($plan instanceof self)return $plan;
        if($plan instanceof TransactionDraft)return new self($plan);
        if(!($plan['action']??null) instanceof TransactionDraft)throw new \InvalidArgumentException('Action draft required');
        $metadata=$plan;unset($metadata['action'],$metadata['approvals'],$metadata['operation']);
        return new self($plan['action'],$plan['approvals']??[],$plan['operation']??null,$metadata);
    }
    public function requiresApprovals(): bool { return $this->approvals!==[]; }
}
