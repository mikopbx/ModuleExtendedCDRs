<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/WorkerDependencyException.php';

use Modules\ModuleExtendedCDRs\Lib\WorkerDependencyException;

$previous = new RuntimeException('secret dependency error');
$error = new WorkerDependencyException('beanstalk_reply', $previous);
if ($error->operation() !== 'beanstalk_reply' || $error->getPrevious() !== $previous) {
    throw new RuntimeException('Dependency exception must retain operation and cause');
}
if (strpos($error->getMessage(), 'secret') !== false) {
    throw new RuntimeException('Dependency exception message must be sanitized');
}

echo "WorkerDependencyExceptionTest: OK\n";
