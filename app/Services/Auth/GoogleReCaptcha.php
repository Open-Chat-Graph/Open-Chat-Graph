<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Config\SecretsConfig;
use Shared\Exceptions\UnauthorizedException;
use Shared\Exceptions\ValidationException;

class GoogleReCaptcha
{
    private const URL = "https://www.google.com/recaptcha/api/siteverify";

    /** @throws UnauthorizedException */
    function validate(string $token, float $maxScore): float
    {
        $data = [
            "secret" => SecretsConfig::$googleRecaptchaSecretKey,
            "response" => $token,
        ];

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents(self::URL, false, $context);

        $result = is_string($response) ? json_decode($response, true) : false;
        if (!$result || !isset($result["success"])) {
            throw new UnauthorizedException('Google reCaptcha is faild');
        }

        if (!$result["success"]) {
            throw new ValidationException('Google reCaptcha result is false');
        }

        if ($result["score"] < $maxScore) {
            throw new ValidationException('Google reCaptcha result is low score');
        }

        return $result["score"];
    }
}
