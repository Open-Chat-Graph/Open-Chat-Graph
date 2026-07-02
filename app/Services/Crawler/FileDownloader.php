<?php

declare(strict_types=1);

namespace App\Services\Crawler;

use Symfony\Component\HttpClient\HttpClient;

class FileDownloader
{
    /**
     * 指定されたURLからファイルをダウンロードする
     *
     * @param string $url ダウンロードするファイルのURL
     * @return string|false ファイルデータ 404の場合はfalse
     * @throws \RuntimeException
     */
    public function downloadFile(
        string $url,
        string $userAgent,
        int $max_redirects = 3,
        int $retryLimit = 3,
        int $retryInterval = 1,
        string $method = 'GET',
        ?float $timeout = null,
        ?float $maxDuration = null,
    ): string|false {
        if (!defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6);
        }

        $httpClient = HttpClient::create();
        $response = null;
        $options =  [
            'headers' => [
                'User-Agent' => $userAgent,
            ],
            'max_redirects' => $max_redirects,
        ];
        // Web リクエスト経路（OGP画像など）でワーカーを不定に拘束しないよう、必要なら上限を掛ける。
        // timeout=通信アイドルの上限 / maxDuration=接続〜受信完了までの総時間の上限（既定 null＝従来どおり無指定）。
        if ($timeout !== null) {
            $options['timeout'] = $timeout;
        }
        if ($maxDuration !== null) {
            $options['max_duration'] = $maxDuration;
        }

        $retryCount = 0;
        $statusCode = 999; // 初期値として不正なステータスコードを設定

        try {
            while ($retryCount < $retryLimit) {
                $response = $httpClient->request($method, $url, $options);
                $statusCode = $response->getStatusCode();
                if ($statusCode === 200) {
                    return $response->getContent();
                } elseif ($statusCode === 404) {
                    return false;
                } else {
                    $retryCount++;
                    sleep($retryInterval);
                }
            }
        } catch (\Throwable $e) {
            if ($response !== null) {
                $response->cancel();
            }

            throw new \RuntimeException(get_class($e) . ': ' . $e->getMessage());
        }

        throw new \RuntimeException($statusCode . ' Server Error: ' . $url);
    }
}
