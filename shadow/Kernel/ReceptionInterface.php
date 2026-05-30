<?php

namespace Shadow\Kernel;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
interface ReceptionInterface
{
    /**
     * Get the value of a request input parameter by name.
     *
     * @param  string|null  $name    The key of a value. It can be accessed using dot notation.
     * @param  mixed        $default
     * @return mixed
     */
    public static function input(?string $name = null, mixed $default = ''): mixed;

    /**
     * Check if a specific input field exists.
     *
     * @param string $name The name of the input field to check.
     * @return bool        Returns true if the input field exists, otherwise returns false.
     */
    public static function has(string $name): bool;

    /**
     * Returns an object version of the input data.
     * 
     * @param string|null $name The name of the property to retrieve as an object. It can be accessed using dot notation.  
     *                          If null, the entire input data is returned as an object.
     * @return \stdClass|null
     */
    public static function getObject(?string $name = null): \stdClass|null;

    /**
     * Overwrites the input data with the given data.
     *
     * @param array $data The data to overwrite the input data with.
     */
    public static function overWrite(array $data);

    /**
     * Determine if the request Content-Type is JSON.
     *
     * @return bool
     */
    public static function isJson(): bool;

    /**
     * Returns the HTTP request method.
     *
     * @return string The HTTP request method (e.g. "GET", "POST", etc.).
     */
    public static function method(): string;

    /**
     * Checks if the HTTP request method matches the given method.
     *
     * @param string $requestMethod The HTTP request method to check (e.g. "GET", "POST", etc.).
     * @return bool                 True if the HTTP request method matches the given method, false otherwise.
     */
    public static function isMethod(string $requestMethod): bool;
}
