<?php

namespace Shadow\Kernel;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
interface ValidatorInterface
{
    const ZERO_WHITE_SPACE = '/[\x{200B}-\x{200D}\x{FEFF}\x{200C}]/u';

    /**
     * Validates a stirng and returns true if it meets the given criteria.
     * 
     * @param mixed $string             The input value to validate.function
     * @param int|null $maxLen          [optional] The maximum length of the string.
     * @param string|array|null $regex  [optional] If specified, the input string must match this regex pattern.
     * @param bool|null $emptyAble      [optional] If the string can be empty or not.
     * @param string|null $e            [optional] An Exception name to be thrown if validation fails.
     * @param ?string $default          [optional] The value to return when the input is empty (applies when $emptyAble is true).
     * 
     * @return string|false        True if the input is valid, otherwise false.
     * 
     * @throws LogicException      If an error occurred in preg_match.
     * 
     * @throws Throwable           If the input string is invalid (not a string), does not match the specified regex pattern,
     *                             or is empty when not allowed.  
     *                             * Error codes:  
     *                             1001 - The input must be a string.  
     *                             1002 - The input string does not match the specified regex pattern.  
     *                             1003 - The input string contains only whitespace characters or an empty string.  
     *                             1004 - The input string exceeds the maximum length limit of {maxLen} characters.  
     */
    public static function str(
        mixed $input,
        ?int $maxLen = null,
        string|array|null $regex = null,
        bool $emptyAble = false,
        ?string $e = null,
        ?string $default = ''
    ): string|false|null;

    /**
     * Validate whether the specified key exists in the array and meets the specified string conditions.
     * 
     * @param array $array         The array to be validated.
     * @param string $key The      key to be validated.
     * @param int|null $maxLen     [optional] The maximum length of the string.
     * @param string|null $regex   [optional] If specified, the input string must match this regex pattern.
     * @param bool     $emptyAble  [optional] Whether the value is empty or passes without a value.
     * @param string|null $e       [optional] An Exception name to be thrown if validation fails.
     * @param ?string $default     [optional] The value to return when the input is empty (applies when $emptyAble is true).
     * 
     * @return string|false        True if the input is valid, otherwise false.
     * 
     * @throws LogicException      If an error occurred in preg_match.
     * 
     * @throws Throwable           If the key exists but validation fails and an exception is specified.valid
     *                             * Error codes:  
     *                             1001 - The input must be a string.  
     *                             1002 - The input string does not match the specified regex pattern.  
     *                             1003 - The input string contains only whitespace characters or an empty string.  
     *                             1004 - The input string exceeds the maximum length limit of {maxLen} characters.  
     */
    public static function arrayStr(
        array $array,
        string $key,
        ?int $maxLen = null,
        string|array|null $regex = null,
        bool $emptyAble = false,
        ?string $e = null,
        ?string $default = ''
    ): string|false|null;

    /**
     * Validates a number and returns true if it meets the given criteria.
     *
     * @param mixed  $input        The input value to validate.
     * @param int|null $max        [optional] The maximum numeric value.
     * @param int|null $min        [optional] The minimum numeric value.
     * @param int|null $exactMatch [optional] The numeric value for exact match.
     * @param bool     $emptyAble  [optional] Whether the value is empty or passes without a value.
     * @param string|null $e       [optional] An Exception name to be thrown if validation fails.
     * @param ?int $default        [optional] The value to return when the input is empty (applies when $emptyAble is true).
     * 
     * @return int|false           True if the input is valid, otherwise false.
     * 
     * @throws Throwable           If the input fails validation.
     *                             * Error codes:  
     *                             2001 - The input must be an integer or a string containing only digits.  
     *                             2002 - The input does not match the expected value.  
     *                             2003 - The input must be greater than or equal to [min].  
     *                             2004 - The input must be less than or equal to [max].  
     */
    public static function num(
        mixed $input,
        ?int $max = null,
        ?int $min = null,
        ?int $exactMatch = null,
        bool $emptyAble = false,
        ?string $e = null,
        ?int $default = 0
    ): int|false|null;

    /**
     * Validate whether the specified key exists in the array and meets the specified numeric conditions.
     *
     * @param array $array         The array to be validated
     * @param string $key          The key to be validated
     * @param int|null $max        [optional] The maximum numeric value.
     * @param int|null $min        [optional] The minimum numeric value.
     * @param int|null $exactMatch [optional] The numeric value for exact match.
     * @param bool     $emptyAble  [optional] Whether the value is empty or passes without a value.
     * @param string|null $e       [optional] An Exception name to be thrown if validation fails.
     * @param ?int $default        [optional] The value to return when the input is empty (applies when $emptyAble is true).
     * 
     * @return int|false           True if the input is valid, otherwise false.
     * 
     * @throws Throwable           If the key exists but validation fails and an exception is specified.
     *                             * Error codes:  
     *                             2001 - The input must be an integer or a string containing only digits.  
     *                             2002 - The input does not match the expected value.  
     *                             2003 - The input must be greater than or equal to [min].  
     *                             2004 - The input must be less than or equal to [max].  
     */
    public static function arrayNum(
        array $array,
        string $key,
        ?int $max = null,
        ?int $min = null,
        ?int $exactMatch = null,
        bool $emptyAble = false,
        ?string $e = null,
        ?int $default = 0
    ): int|false|null;

    /**
     * Validates an uploaded file based on the allowed mime types and maximum file size.
     * 
     * @param array  $file             Array of the file input element.
     * @param array  $allowedMimeTypes Array of allowed mime types for the file.
     * @param ?int   $maxFileSize      Maximum allowed file size in kilobytes (KB).
     * 
     * @return array Returns an array containing information about the uploaded file if it passes validation.
     * 
     * @throws ValidationException If the file is too large, has an extension not allowed,
     *                             or has a mime type that does not match the file type.  
     *                             * Error codes:  
     *                             3000 - If the file is not uploaded via HTTP POST.
     *                             3001 - File too large.  
     *                             3002 - File extension not allowed.  
     *                             3003 - File type does not match.  
     */
    public static function uploadedFile(array $file, array $allowedMimeTypes, ?int $maxFileSize = null): array;
}
