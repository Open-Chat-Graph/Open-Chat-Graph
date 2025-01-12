<?php

declare(strict_types=1);

namespace Shadow\Kernel;

use Shared\Exceptions\ValidationException;
use Shared\MimimalCmsConfig;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
class Validator implements ValidatorInterface
{
    public static function str(
        mixed $input,
        ?int $maxLen = null,
        string|array|null $regex = null,
        bool $emptyAble = false,
        ?string $e = null,
        ?string $default = ''
    ): string|false|null {
        if ((is_null($input) || $input === '') && $emptyAble) {
            return $default;
        }

        if (!is_string($input)) {
            if ($e === null) return false;
            $errorCode = 1001;
            $errorMessage = 'The input must be a string.';
            throw new $e($errorMessage, $errorCode);
        }

        if (is_string($regex)) {
            if (!self::preg_match($regex, $input, $e)) {
                return false;
            }
        }

        if (is_array($regex)) {
            $pattern = '';
            $isFirstElement = true;
            foreach ($regex as $r) {
                if ($isFirstElement) {
                    $isFirstElement = false;
                } else {
                    $pattern .= '|';
                }

                $pattern .= preg_quote((string)$r, '/');
            }

            if (!self::preg_match("/^(" . $pattern  . ")$/", $input, $e)) {
                return false;
            }
        }

        if (!$emptyAble) {
            $normalizedStr = $input;
            if (class_exists('Normalizer')) {
                $normalizedStr = \Normalizer::normalize($input, \Normalizer::FORM_KC);
            }

            if (is_string($normalizedStr)) {
                $replaceStr = preg_replace(ValidatorInterface::ZERO_WHITE_SPACE, '', $normalizedStr);

                if ($replaceStr === null || trim($replaceStr) === '') {
                    if ($e === null) return false;
                    $errorCode = 1003;
                    $errorMessage = 'The input string contains only whitespace characters or an empty string.';
                    throw new $e($errorMessage, $errorCode);
                }
            }
        }

        if ($maxLen !== null && mb_strlen($input) > $maxLen) {
            if ($e === null) return false;
            $errorCode = 1004;
            $errorMessage = 'The input string exceeds the maximum length limit of ' . $maxLen . ' characters.';
            throw new $e($errorMessage, $errorCode);
        }

        return $input;
    }

    protected static function preg_match(array|string $regex, string $input, ?string $e): bool
    {
        $result = @preg_match($regex, $input);
        if ($result === false) {
            $errorCode = 1000;
            $errorMessage = 'An error occurred while executing preg_match function. Please check the regex pattern.';
            throw new \LogicException($errorMessage, $errorCode);
        }

        if ($result === 0) {
            if ($e === null) return false;
            $errorCode = 1002;
            $errorMessage = 'The input string does not match the specified regex pattern.';
            throw new $e($errorMessage, $errorCode);
        }

        return true;
    }

    public static function arrayStr(
        array $array,
        string $key,
        ?int $maxLen = null,
        string|array|null $regex = null,
        bool $emptyAble = false,
        ?string $e = null,
        ?string $default = ''
    ): string|false|null {
        return self::str($array[$key] ?? null, $maxLen, $regex, $emptyAble, $e, $default);
    }

    public static function num(
        mixed $input,
        ?int $max = null,
        ?int $min = null,
        ?int $exactMatch = null,
        bool $emptyAble = false,
        ?string $e = null,
        ?int $default = 0
    ): int|false|null {
        if ((is_null($input) || $input === '') && $emptyAble) {
            return $default;
        }

        if (!is_int($input) && (!is_string($input) || !ctype_digit($input))) {
            if ($e === null) return false;
            $errorCode = 2001;
            $errorMessage = 'The input must be an integer or a string containing only digits.';
            throw new $e($errorMessage, $errorCode);
        }

        $number = (int) $input;

        if ($exactMatch !== null && $number !== $exactMatch) {
            if ($e === null) return false;
            $errorCode = 2002;
            $errorMessage = 'The input does not match the expected value.';
            throw new $e($errorMessage, $errorCode);
        }

        if ($min !== null && $number < $min) {
            if ($e === null) return false;
            $errorCode = 2003;
            $errorMessage = 'The input must be greater than or equal to ' . $min . '.';
            throw new $e($errorMessage, $errorCode);
        }

        if ($max !== null && $number > $max) {
            if ($e === null) return false;
            $errorCode = 2004;
            $errorMessage = 'The input must be less than or equal to ' . $max . '.';
            throw new $e($errorMessage, $errorCode);
        }

        return (int) $input;
    }

    public static function arrayNum(
        array $array,
        string $key,
        ?int $max = null,
        ?int $min = null,
        ?int $exactMatch = null,
        bool $emptyAble = false,
        ?string $e = null,
        ?int $default = 0
    ): int|false|null {
        return self::num($array[$key] ?? null, $max, $min, $exactMatch, $emptyAble, $e, $default);
    }

    public static function uploadedFile(array $file, array $allowedMimeTypes, ?int $maxFileSize = null): array
    {
        if (
            !isset($file['tmp_name'])
            || !is_string($file['tmp_name'])
            || !is_uploaded_file($file['tmp_name'])
        ) {
            $errorCode = 3000;
            $errorMessage = 'Invalid file.';
            throw new ValidationException($errorMessage, $errorCode);
        }

        $maxFileSize = $maxFileSize ?? MimimalCmsConfig::$defaultMaxFileSize;
        if ($file['size'] > $maxFileSize * 1024) {
            $errorCode = 3001;
            $errorMessage = 'File too large.';
            throw new ValidationException($errorMessage, $errorCode);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $errorCode = 3002;
            $errorMessage = 'File extension not allowed.';
            throw new ValidationException($errorMessage, $errorCode);
        }

        if ($mimeType !== $file['type']) {
            $errorCode = 3003;
            $errorMessage = 'File type does not match.';
            throw new ValidationException($errorMessage, $errorCode);
        }

        return $file;
    }
}
