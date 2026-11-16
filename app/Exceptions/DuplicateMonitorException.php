<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class DuplicateMonitorException extends Exception
{
    public static function forUrl(string $url): self
    {
        return new self(sprintf('A monitor for URL [%s] already exists in this workspace.', $url));
    }

    public static function forName(string $name): self
    {
        return new self(sprintf('A monitor with name [%s] already exists in this workspace.', $name));
    }
}
