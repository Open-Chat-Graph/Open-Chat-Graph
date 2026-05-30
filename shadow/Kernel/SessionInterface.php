<?php

namespace Shadow\Kernel;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
interface SessionInterface
{
    /**
     * Adds a value to the session as a flash message.
     *
     * Flash messages are only available for one request, and are then deleted.
     *
     * @param string|array $key   The key to add the value to or an associative array of key-value pairs.
     * @param mixed        $value [optional] The value to add to the session.
     */
    public static function flash(string|array $key, mixed $value = null);

    /**
     * Pushes one or multiple values to the session.
     *
     * @param string|array $key   The key to add the value to or an associative array of key-value pairs.
     * @param mixed        $value [optional] The value to add to the session.
     */
    public static function push(string|array $key, mixed $value = null);

    /**
     * Gets a value from the session and/or flash session.
     *
     * @param string $key The key of the value to get.
     * 
     * @return mixed|null The value if found, null otherwise.
     */
    public static function get(string $key, mixed $default = null): mixed;

    /**
     * Removes a value from the session and/or flash session.
     *
     * @param string $key The key of the value to remove.
     * 
     * @return bool       True if the value was found and removed, false otherwise.
     */
    public static function remove(string $key): bool;

    /**
     * Clears the session and flash session.
     */
    public static function flush();

    /**
     * Check if a key exists in any session.
     *
     * @param string $key The key to check.
     * 
     * @return bool       True if the key exists in any session, false otherwise.
     */
    public static function has(string $key): bool;

    /**
     * Adds an error message to the session's error array.
     *
     * @param string    $key     The key
     * @param int|mixed $code    [optional] The error code.
     * @param string    $message [optional] The error message.
     */
    public static function addError(string $key, int $code = 0, string $message = '');

    /**
     * Gets the error message value from the session's error array by key.
     *
     * @param string|null $key The key of the error message value to retrieve. If null, all errors are returned.
     * 
     * @return array The error value if found, an empty array otherwise.
     *               If $key is null, an associative array of error keys and values is returned.
     *               Otherwise, an array with 'code' and 'message' keys, representing the error code and message, respectively,
     *               is returned for the specified key.
     * 
     *               * **Example for a specific key:** `['code' => 1001, 'message' => 'The input must be a string.']`
     *               * **Example for all errors:** `['key1' => ['code' => 1001, 'message' => 'Error message 1'], 'key2' => ['code' => 1002, 'message' => 'Error message 2']]`
     */
    public static function getError(?string $key = null): array;

    /**
     * Gets the error message string from the session's error array by key.
     *
     * @param string $key The key of the error message value to retrieve.
     * 
     * @return string|null The error message string if found, null otherwise.
     */
    public static function getErrorMessage(string $key): ?string;

    /**
     * Gets the error code value from the session's error array by key.
     *
     * @param string $key The key of the error message value to retrieve.
     * 
     * @return int|null The error code value if found, null otherwise.
     */
    public static function getErrorCode(string $key): ?int;

    /**
     * Checks if an error message exists in the session's error array by key.
     *
     * @param string|null $key The key of the error message to check.
     * @return bool            True if the error message exists, false otherwise.
     */
    public static function hasError(?string $key = null): bool;

    /**
     * Save input values to session, excluding specified names (if provided).
     * 
     * @param string ...$exceptNames The names of form inputs to exclude from being flashed.
     */
    public static function flashInput(string ...$exceptNames);
}

