<?php

declare(strict_types=1);

namespace App\Modules\Sync\Enums;

enum SyncDirection: string
{
    case Push = 'push';
    case Pull = 'pull';
}
