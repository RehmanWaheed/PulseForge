<?php

declare(strict_types=1);

namespace App\Enums;

enum MonitorMethod: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Head = 'HEAD';
}
