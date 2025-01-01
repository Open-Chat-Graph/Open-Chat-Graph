<?php

declare(strict_types=1);

namespace App\Exceptions\Handlers;

use Shadow\ApplicationExceptionHandlerInterface;

class ApplicationExceptionHandler implements ApplicationExceptionHandlerInterface
{
    public static array $exceptionMap = [
        \App\Exceptions\ApplicationException::class => 'app',
    ];

    public static function handleException(\Throwable $e, string $className)
    {
        echo static::$exceptionMap[$className] . ': ';
        echo $e->getMessage();
    }
}
