<?php

/**
 * MimimalCMS0.1
 * 
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */

// const URL_ROOT = '';
const PUBLIC_DIR = __DIR__ . '/../public';
const VIEWS_DIR = __DIR__ . '/../app/Views';
const JSON_STORAGE_DIR =  __DIR__ . '/../storage/json';
const CONFIG_JSON_FILE_PATH = __DIR__ . '/../app/Config/ConfigJson.json';

// Default options for cookies.
//const COOKIE_DEFAULT_SECURE = false;
const COOKIE_DEFAULT_HTTPONLY = true;
const COOKIE_DEFAULT_SAMESITE = 'lax';

// Options for session.
const FLASH_SESSION_KEY_NAME = 'mimimalFlashSession';
const SESSION_KEY_NAME = 'mimimalSession';

// File validator.
const DEFAULT_MAX_FILE_SIZE = 20480;
const URL_STRING_PATTERN = '/^[a-zA-Z0-9-._~!$&\'()*+,;=:@\/?%]+$/';
const RELATIVE_PATH_PATTERN = '/^(?!(?:f|ht)tps?:\/\/)/i';

date_default_timezone_set('Asia/Tokyo');

if (($_SERVER['HTTP_HOST'] ?? '') === 'openchat-review.me') {
    $_SERVER['HTTPS'] = 'on';
    define('SESSION_COOKIE_PARAMS', [
        'secure' => true,
        'httponly' => true,
        'samesite' => 'lax',
    ]);

    define('COOKIE_DEFAULT_SECURE', true);
} else {
    define('SESSION_COOKIE_PARAMS', [
        'secure' => false,
        'httponly' => true,
        'samesite' => 'lax',
    ]);

    define('COOKIE_DEFAULT_SECURE', false);
}

(function () {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match("{^/th/.*}", $requestUri) || preg_match("{^/th$}", $requestUri)) {
        define('URL_ROOT', '/th');
        /*     define('PUBLIC_DIR', __DIR__ . '/../public/th');
        define('VIEWS_DIR', __DIR__ . '/../app/Views/th');
        define('JSON_STORAGE_DIR', __DIR__ . '/../storage/json/th');
        define('CONFIG_JSON_FILE_PATH', __DIR__ . '/../app/Config/ConfigJson_th.json'); */
    } else if (preg_match("{^/tw/.*}", $requestUri) || preg_match("{^/tw$}", $requestUri)) {
        define('URL_ROOT', '/tw');
    } else {
        define('URL_ROOT', '');
    }
})();
