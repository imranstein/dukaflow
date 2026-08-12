<?php

declare(strict_types=1);

namespace App\Modules\Sync\Exceptions;

use DomainException;

final class SyncPushException extends DomainException
{
    public static function customerNotOnRoute(): self
    {
        return new self('This customer is not on your route.');
    }
}
