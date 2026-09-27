<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class RentalHireStatus
{
    public const ACCOUNT_ACTIVE = 'active';
    public const ACCOUNT_CLOSED = 'closed';

    public const AGREEMENT_DRAFT = 'draft';
    public const AGREEMENT_ACTIVE = 'active';
    public const AGREEMENT_SUSPENDED = 'suspended';
    public const AGREEMENT_ENDED = 'ended';
    public const AGREEMENT_CANCELLED = 'cancelled';

    public const CHARGE_DUE = 'due';
    public const CHARGE_PARTIAL = 'partially_paid';
    public const CHARGE_PAID = 'paid';
    public const CHARGE_OVERDUE = 'overdue';
    public const CHARGE_VOID = 'void';

    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_RECEIVED = 'received';
    public const PAYMENT_FAILED = 'failed';
    public const PAYMENT_REVERSED = 'reversed';

    public const RECEIPT_ISSUED = 'issued';
    public const RECEIPT_VOID = 'void';
}
