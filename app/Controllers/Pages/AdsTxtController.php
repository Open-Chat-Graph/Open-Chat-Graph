<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Config\GoogleAdsenseConfig;

class AdsTxtController
{
    public function index()
    {
        // Content-Typeをtext/plainに設定
        header('Content-Type: text/plain; charset=UTF-8');

        echo $this->getAdsTxt();

        exit;
    }

    /**
     * ads.txtの内容を取得
     */
    private function getAdsTxt(): string
    {
        // SecretsConfigから設定を取得（空の場合は何も出力しない）
        $publisherId = str_replace('ca-', '', GoogleAdsenseConfig::$googleAdsenseClient);
        if (empty($publisherId)) {
            return '';
        }

        $contactUrl = AppConfig::$siteDomain . '/policy';

        return <<<TXT
google.com, {$publisherId}, DIRECT, f08c47fec0942fa0

contact={$contactUrl}
TXT;
    }
}
