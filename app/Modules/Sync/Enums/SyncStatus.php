<?php

declare(strict_types=1);

namespace App\Modules\Sync\Enums;

enum SyncStatus: string
{
    case Ok = 'ok';
    case Conflict = 'conflict';
    case Error = 'error';
}
