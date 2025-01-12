<?php

declare(strict_types=1);

namespace Shadow\Kernel;

use Shared\MimimalCmsConfig;

/**
 * Cookie class for handling HTTP cookies.
 * 
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
class Cookie implements CookieInterface
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_COOKIE[$key] ?? $default;
    }

    public static function push(
        string|array $key,
        mixed $value = null,
        int $expires = 0,
        string $path = '/',
        ?string $samesite = null,
        ?bool $secure = null,
        ?bool $httpOnly = null,
        string $domain = ''
    ) {
        $options = [
            'expires' => $expires,
            'path' => $path,
            'samesite' => $samesite ?? MimimalCmsConfig::$cookieDefaultSameSite,
            'secure' => $secure ?? MimimalCmsConfig::$cookieDefaultSecure,
            'httponly' => $httpOnly ?? MimimalCmsConfig::$cookieDefaultHttpOnly,
            'domain' => $domain
        ];

        if (is_array($key)) {
            if ($expires === 0 && is_int($value)) {
                $options['expires'] = $value;
            }

            foreach ($key as $name => $val) {
                setcookie($name, $val, $options);
            }
        } else {
            setcookie($key, $value, $options);
        }
    }

    public static function remove(string $key): bool
    {
        if (!isset($_COOKIE[$key])) {
            return false;
        }

        setcookie($key, '', time() - 3600, "/");
        unset($_COOKIE[$key]);
        return true;
    }

    public static function flush()
    {
        foreach ($_COOKIE as $key => $value) {
            setcookie($key, '', time() - 3600, "/");
        }

        $_COOKIE = [];
    }

    public static function has(string $key): bool
    {
        return isset($_COOKIE[$key]);
    }

    public static function csrfToken()
    {
        if (!isset($_SESSION['_csrf']) || !isset($_COOKIE['CSRF-Token'])) {
            $token = bin2hex(random_bytes(16));
            $_SESSION['_csrf'] = hash('sha256', $token);
            self::push(['CSRF-Token' => $token]);
        }
    }

    public static function refreshCsrfToken()
    {
        $token = bin2hex(random_bytes(16));
        $_SESSION['_csrf'] = hash('sha256', $token);
        self::push(['CSRF-Token' => $token]);
    }
}
