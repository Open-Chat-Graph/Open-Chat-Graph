<?php

declare(strict_types=1);

namespace Shadow\Kernel;

use Shared\MimimalCmsConfig;

/**
 * Handle sessions and flash messages.
 * 
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
class Session implements SessionInterface
{
    public static function push(string|array $key, mixed $value = null)
    {
        if (is_array($key)) {
            if (!isset($_SESSION[MimimalCmsConfig::$sessionKeyName]) || !is_array($_SESSION[MimimalCmsConfig::$sessionKeyName])) {
                $_SESSION[MimimalCmsConfig::$sessionKeyName] = [];
            }

            $_SESSION[MimimalCmsConfig::$sessionKeyName] = array_merge($_SESSION[MimimalCmsConfig::$sessionKeyName], $key);
            return;
        }

        $_SESSION[MimimalCmsConfig::$sessionKeyName][$key] = $value;
    }

    public static function flash(string|array $key, mixed $value = null)
    {
        if (is_array($key)) {
            if (!isset($_SESSION[MimimalCmsConfig::$flashSessionKeyName]) || !is_array($_SESSION[MimimalCmsConfig::$flashSessionKeyName])) {
                $_SESSION[MimimalCmsConfig::$flashSessionKeyName] = [];
            }

            $_SESSION[MimimalCmsConfig::$flashSessionKeyName] = array_merge($_SESSION[MimimalCmsConfig::$flashSessionKeyName], $key);
            return;
        }

        $_SESSION[MimimalCmsConfig::$flashSessionKeyName][$key] = $value;
    }

    public static function remove(string $key): bool
    {
        if (isset($_SESSION[MimimalCmsConfig::$sessionKeyName][$key])) {
            unset($_SESSION[MimimalCmsConfig::$sessionKeyName][$key]);
            return true;
        }

        return false;
    }

    public static function flush()
    {
        Reception::$flashSession = [];
        $_SESSION[MimimalCmsConfig::$sessionKeyName] = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (isset(Reception::$flashSession[$key])) {
            return Reception::$flashSession[$key];
        }

        if (isset($_SESSION[MimimalCmsConfig::$sessionKeyName][$key])) {
            return $_SESSION[MimimalCmsConfig::$sessionKeyName][$key];
        }

        return $default;
    }

    public static function has(string $key): bool
    {
        if (isset(Reception::$flashSession[$key])) {
            return true;
        }

        if (isset($_SESSION[MimimalCmsConfig::$sessionKeyName][$key])) {
            return true;
        }

        return false;
    }

    public static function addError(string $key, int $code = 0, string $message = '')
    {
        $_SESSION[MimimalCmsConfig::$flashSessionKeyName]['ERRORS_ARRAY'][$key] = ['code' => $code, 'message' => $message];
    }

    public static function getError(?string $key = null): array
    {
        if ($key === null) {
            return Reception::$flashSession['ERRORS_ARRAY'] ?? [];
        }

        return Reception::$flashSession['ERRORS_ARRAY'][$key] ?? [];
    }

    public static function getErrorMessage(string $key): ?string
    {
        return Reception::$flashSession['ERRORS_ARRAY'][$key]['message'] ?? null;
    }

    public static function getErrorCode(string $key): ?int
    {
        return Reception::$flashSession['ERRORS_ARRAY'][$key]['code'] ?? null;
    }

    public static function hasError(?string $key = null): bool
    {
        if ($key === null) {
            return is_array(Reception::$flashSession['ERRORS_ARRAY'] ?? null)
                && !empty(Reception::$flashSession['ERRORS_ARRAY']);
        }

        return isset(Reception::$flashSession['ERRORS_ARRAY'][$key]);
    }

    public static function flashInput(string ...$exceptNames)
    {
        $input = Reception::$inputData;

        foreach ($exceptNames as $key) {
            if (array_key_exists($key, $input)) {
                unset($input[$key]);
            }
        }

        $_SESSION[MimimalCmsConfig::$flashSessionKeyName]['OLD_ARRAY'] = $input;
    }
}
