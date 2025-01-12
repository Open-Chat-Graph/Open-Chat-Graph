<?php

namespace Shadow\Kernel;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
interface CookieInterface
{
    /**
     * Pushes one or multiple values to the cookie.
     *
     * @param string|array $key   The key to add the value to or an associative array of key-value pairs.
     * @param mixed        $value [optional] The value to add to the cookie.
     * @return void
     */
    public static function push(
        string|array $key,
        mixed $value = null,
        int $expires = 0,
        string $path = '/',
        ?string $samesite = null,
        ?bool $secure = null,
        ?bool $httpOnly = null,
        string $domain = ''
    );

    /**
     * Gets a value from the cookie.
     *
     * @param string $key The key of the value to get.
     * @return mixed|null The value if found, null otherwise.
     */
    public static function get(string $key, mixed $default = null): mixed;

    /**
     * Removes a value from the cookie.
     *
     * @param string $key The key of the value to remove.
     * @return bool       True if the value was found and removed, false otherwise.
     */
    public static function remove(string $key): bool;

    /**
     * Clears all cookies.
     *
     * @return void
     */
    public static function flush();

    /**
     * Check if a key exists in any cookie.
     *
     * @param string $key The key to check.
     * @return bool       True if the key exists in any cookie, false otherwise.
     */
    public static function has(string $key): bool;


    /**
     * Generate a CSRF token and store it in the session and cookie.
     */
    public static function csrfToken();

    /**
     * Refresh CSRF token in the session and cookie.
     */
    public static function refreshCsrfToken();
}