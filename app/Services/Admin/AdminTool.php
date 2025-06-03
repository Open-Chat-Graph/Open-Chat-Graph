<?php

namespace App\Services\Admin;

use App\Config\AppConfig;
use App\Config\SecretsConfig;
use Shared\MimimalCmsConfig;
use Symfony\Component\HttpClient\HttpClient;

class AdminTool
{
    static function sendPutRequestAdminApiSelf(string $url, array $json = []): string|int
    {
        if (!defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6);
        }

        $json['key'] = SecretsConfig::$adminApiKey;
        $options =  compact('json');

        $httpClient = HttpClient::create();
        $response = $httpClient->request('PUT', $url, $options);

        $statusCode = $response->getStatusCode();
        if ($statusCode === 200) {
            return $response->getContent();
        } else {
            return $statusCode;
        }
    }

    static function sendGetRequest(string $url, string $cookie = ''): string|int
    {
        if (!defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6);
        }

        $options = [
            'headers' => [
                'Cookie' => $cookie,
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36'
            ],
        ];

        $httpClient = HttpClient::create();
        $response = $httpClient->request('GET', $url, $options);

        $statusCode = $response->getStatusCode();
        if ($statusCode === 200) {
            return $response->getContent();
        } else {
            return $statusCode;
        }
    }

    static function sendPostFile(string $url, array $files): string
    {
        foreach ($files as $name => $filePath) {
            $files[$name] = new \CURLFile($filePath);
        }

        $header = ['Content-Type: multipart/form-data'];
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POST            => true,
            CURLOPT_HTTPHEADER      => $header,
            CURLOPT_POSTFIELDS      => $files
        ];

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        curl_close($ch);

        return (string)$response;
    }

    static function sendDiscordNotify(string $message, ?string $webhookUrl = null): string
    {
        $webhookUrl = $webhookUrl ?? SecretsConfig::$discordWebhookUrl;

        $curl = function ($message) use ($webhookUrl) {
            $payload = json_encode(['content' => $message]);
            $header = ['Content-Type: application/json'];
            $ch = curl_init($webhookUrl);
            $options = [
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_POST            => true,
                CURLOPT_HTTPHEADER      => $header,
                CURLOPT_POSTFIELDS      => $payload
            ];

            curl_setopt_array($ch, $options);
            $response = curl_exec($ch);
            curl_close($ch);

            return (string)$response;
        };

        $urlRoot = '[' . strtoupper(str_replace('/', '', MimimalCmsConfig::$urlRoot) ?: 'JA') . '] ';
        $isStaging = AppConfig::$isStaging ? '[STG] ' : '';
        $isDev = AppConfig::$isDevlopment && !AppConfig::$isStaging ? '[DEV] ' : '';
        $prefix =  $isStaging . $isDev . $urlRoot . "\n";

        $responses = [];
        foreach (mb_str_split($message, 2000 - mb_strlen($prefix)) as $el) {
            $responses[] = $curl($prefix . $el);
        }

        return implode("\n", $responses);
    }
}
