<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Exceptions;

use DomainException;

final class VisitOutcomeException extends DomainException
{
    public static function reasonRequired(): self
    {
        return new self('A no-sale outcome needs a reason. It is worthless to a manager without one.');
    }
}
