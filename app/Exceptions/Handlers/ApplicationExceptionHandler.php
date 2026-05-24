<?php

declare(strict_types=1);

namespace App\Exceptions\Handlers;

use Shadow\ApplicationExceptionHandlerInterface;

class ApplicationExceptionHandler implements ApplicationExceptionHandlerInterface
{
    public static array $exceptionMap = [
        \App\Exceptions\ApplicationException::class => 'app',
        \App\Exceptions\GoneException::class => 'gone',
    ];

    public static function handleException(\Throwable $e, string $className)
    {
        if ($className === \App\Exceptions\GoneException::class) {
            http_response_code(410);
            return;
        }

        echo static::$exceptionMap[$className] . ': ';
        echo $e->getMessage();
    }
}
