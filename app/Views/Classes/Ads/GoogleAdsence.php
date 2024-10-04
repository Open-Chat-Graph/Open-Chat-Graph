<?php

namespace App\Views\Ads;

class GoogleAdsence
{
    const AD_CLIENT = 'ca-pub-2330982526015125';

    const AD_SLOTS = [
        // OCトップ-rectangle
        'ocTopRectangle' => ['8037531176', 'rectangle-ads'],

        // OCトップ2-横長
        'ocTopWide2' => ['6469006397', 'rectangle2-ads'],

        // OCセパレーター-レスポンシブ
        'ocSeparatorResponsive' => ['2542775305'],

        // OCセパレーター-横長
        'ocSeparatorWide' => ['1847273098', 'rectangle2-ads'],

        // サイトトップ-rectangle
        'siteTopRectangle' => ['4122044659', 'rectangle-ads'],

        // サイトトップ2-横長
        'siteTopWide' => ['4015067592', 'rectangle2-ads'],

        // サイトセパレーター-レスポンシブ
        'siteSeparatorResponsive' => ['4243068812'],

        // おすすめトップ-rectangle
        'recommendTopRectangle' => ['3109180036', 'rectangle-ads'],

        // おすすめトップ2-横長
        'recommendTopWide2' => ['1796098364', 'rectangle2-ads'],

        // おすすめセパレーター-レスポンシブ
        'recommendSeparatorResponsive' => ['7064673271'],
    ];

    /**
     * @param array $adElement { 0: int, 1: string }|{ 0: int }
     */
    static function output(array $adElement)
    {
        if (count($adElement) === 1) {
            self::responsive($adElement[0], 'responsive-google');
        } else {
            self::rectangle($adElement[0], $adElement[1]);
        }
    }

    private static function rectangle(int $adSlot, string $cssClass)
    {
        $adClient = self::AD_CLIENT;
        echo <<<EOT
        <ins class="adsbygoogle {$cssClass}" data-ad-client="{$adClient}" data-ad-slot="{$adSlot}"></ins>
        EOT;
    }

    private static function responsive(int $adSlot, string $cssClass)
    {
        $adClient = self::AD_CLIENT;
        echo <<<EOT
        <ins class="adsbygoogle {$cssClass}" data-ad-client="{$adClient}" data-ad-slot="{$adSlot}" data-ad-format="auto" data-full-width-responsive="true"></ins>
        EOT;
    }
}
