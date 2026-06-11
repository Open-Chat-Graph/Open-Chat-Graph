<?php

/**
 * MimimalCMS v1
 * 
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */

use Shared\MimimalCmsConfig;

if (file_exists(__DIR__ . '/secrets.php')) {
    require_once __DIR__ . '/secrets.php';
}

if (file_exists(__DIR__ . '/../prod-secrets.php')) {
    require_once __DIR__ . '/../prod-secrets.php';
}

if (file_exists(__DIR__ . '/../local-secrets.php')) {
    require_once __DIR__ . '/../local-secrets.php';
}

date_default_timezone_set('Asia/Tokyo');

$httpHost = 'openchat-review.me';

if (
    ($_SERVER['HTTP_HOST'] ?? '') === $httpHost
    || ($_SERVER['HTTPS'] ?? '') === 'on'
) {
    $_SERVER['HTTPS'] = 'on';
    MimimalCmsConfig::$cookieDefaultSecure = true;
} else {
    MimimalCmsConfig::$cookieDefaultSecure = false;
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '';

if (preg_match("{^/th/.*}", $requestUri) || $requestUri === '/th') {
    MimimalCmsConfig::$urlRoot = '/th';
} else if (preg_match("{^/tw/.*}", $requestUri) || $requestUri === '/tw') {
    MimimalCmsConfig::$urlRoot = '/tw';
} else {
    MimimalCmsConfig::$urlRoot = '';
}
