<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

use RuntimeException;
use Throwable;

final class WorkerDependencyException extends RuntimeException
{
    private string $operation;

    public function __construct(string $operation, Throwable $previous)
    {
        parent::__construct('Worker dependency operation failed', 0, $previous);
        $this->operation = $operation;
    }

    public function operation(): string
    {
        return $this->operation;
    }
}
