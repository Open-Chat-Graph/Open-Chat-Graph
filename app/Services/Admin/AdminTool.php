<?php

namespace App\Services\Admin;

use App\Config\AdminConfig;
use Symfony\Component\HttpClient\HttpClient;

class AdminTool
{
    static function sendPutRequestAdminApiSelf(string $url, array $json = []): string|int
    {
        if (!defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6);
        }

        $json['key'] = AdminConfig::ADMIN_API_KEY;
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

    static function sendLineNofity(string $message, string $token = AdminConfig::LINE_NOTIFY_TOKEN): string
    {
        $curl = function ($message) use ($token) {
            $query = http_build_query(['message' => $message]);
            $header = ['Authorization: Bearer ' . $token];
            $ch = curl_init('https://notify-api.line.me/api/notify');
            $options = [
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_POST            => true,
                CURLOPT_HTTPHEADER      => $header,
                CURLOPT_POSTFIELDS      => $query
            ];

            curl_setopt_array($ch, $options);
            $response = curl_exec($ch);
            curl_close($ch);

            return (string)$response;
        };

        $responses = [];
        foreach (mb_str_split($message, 1000) as $el) {
            $responses[] = $curl($el);
        }

        return implode("\n", $responses);
    }
}
