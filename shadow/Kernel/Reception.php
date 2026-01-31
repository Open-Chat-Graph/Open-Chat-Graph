<?php

declare(strict_types=1);

namespace Shadow\Kernel;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
class Reception implements ReceptionInterface
{
    public static string $domain;
    public static string $requestMethod = 'HEAD';
    public static bool $isJson = false;
    public static array $flashSession = [];
    public static array $inputData = [];

    public static function overWrite(array $data)
    {
        self::$inputData = $data;
    }

    public static function input(?string $name = null, mixed $default = null): mixed
    {
        if ($name === null) {
            return self::$inputData ?? [];
        }

        $data = self::$inputData;
        foreach (explode('.', $name) as $property) {
            $data = &$data[$property] ?? null;
        }

        return $data ?? $default;
    }

    public static function has(string $name): bool
    {
        $data = self::$inputData[$name] ?? null;
        return $data === null ? false : true;
    }

    public static function getObject(?string $name = null): \stdClass|null
    {
        $array = self::$inputData ?? [];

        if ($name !== null) {
            foreach (explode('.', $name) as $property) {
                $array = &$array[$property] ?? null;
            }

            if ($array === null || !is_array($array)) {
                return null;
            }
        }

        return self::objectRecursive($array);
    }

    protected static function objectRecursive(array $array)
    {
        $result = new \stdClass();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result->$key = self::objectRecursive($value);
            } else {
                $result->$key = $value;
            }
        }

        return $result;
    }

    public static function isJson(): bool
    {
        return self::$isJson ?? false;
    }

    
    public static function isMethod(string $requestMethod): bool
    {
        return strtoupper($requestMethod) === (self::$requestMethod ?? '');
    }

    public static function method(): string
    {
        return self::$requestMethod ?? '';
    }
}
