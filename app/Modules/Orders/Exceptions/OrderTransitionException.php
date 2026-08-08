<?php

declare(strict_types=1);

namespace App\Modules\Orders\Exceptions;

use App\Modules\Orders\Enums\OrderStatus;
use DomainException;

/**
 * Thrown when something asks an order to do what its state does not allow.
 *
 * It throws rather than returning false because a caller asking for an
 * impossible transition has a bug. Swallowing it leaves an order sitting in a
 * state nobody chose, which is worse than a stack trace.
 */
final class OrderTransitionException extends DomainException
{
    public static function cannotMove(OrderStatus $from, OrderStatus $to): self
    {
        if ($from->isFinal()) {
            return new self(
                "A {$from->label()} order is final and cannot become {$to->label()}."
            );
        }

        $allowed = implode(', ', array_map(
            static fn (OrderStatus $status): string => $status->label(),
            $from->allowedNext(),
        ));

        return new self(
            "A {$from->label()} order cannot become {$to->label()}. It can only become: {$allowed}."
        );
    }

    public static function cannotSubmitWithoutLines(): self
    {
        return new self('An order with no lines cannot be submitted.');
    }

    public static function linesAreFrozen(OrderStatus $status): self
    {
        return new self(
            "Lines cannot be changed on a {$status->label()} order. Only a draft can be edited."
        );
    }
}
