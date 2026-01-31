<?php

namespace Shadow\Kernel;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
interface ResponseInterface
{
    /**
     * Sets a flash session value or values by key.
     * If the given key is an array, it merges it with the existing flash session.
     *
     * @param string|array       $key   The key or array of keys to set in the flash session.
     * @param mixed     　　    　$value The value to set.
     * @return ResponseInterface        This instance for method chaining.
     */
    public function with(string|array $key, mixed $value = null): ResponseInterface;

    /**
     * Adds an error message to the session's error array.
     * If the given key is an array, it merges it with the existing errors array.
     *
     * @param string|array $key     The key or array of keys to add to the errors array.
     * @param int          $code    The error code.
     * @param string       $message The error message.
     */
    public function withErrors(string $key, int $code = 0, string $message = ''): ResponseInterface;

    /**
     * Save input values to session, excluding specified names (if provided).
     *
     * @param string ...$exceptNames The names of form inputs to exclude from being flashed.
     */
    public function withInput(string ...$exceptNames): ResponseInterface;


    /**
     * Returns HTTP status code and response.
     */
    public function send();

    /**
     * Get the response body content as a string.
     * For JSON responses, returns the JSON-encoded string.
     *
     * @return string The response body content.
     */
    public function getBody(): string;
}
