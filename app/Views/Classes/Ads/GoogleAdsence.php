<?php

namespace App\Views\Ads;

use App\Config\AppConfig;

class GoogleAdsence
{
    const AD_CLIENT = 'ca-pub-2330982526015125';

    const AD_SLOTS = [
        // OCトップ-rectangle
        'ocTopRectangle' => ['8037531176', 'rectangle-ads manual'],

        // OCトップ-rectangle
        'ocTopRectanglePreload' => ['8037531176', 'rectangle-ads preload'],

        // OCトップ2-横長
        'ocTopWide2' => ['6469006397', 'rectangle2-ads manual'],

        // OCトップ2-横長
        'ocTopWide2Preload' => ['6469006397', 'rectangle2-ads preload'],

        // OCセパレーター-レスポンシブ
        'ocSeparatorResponsive' => ['2542775305'],

        // OCセパレーター-rectangle
        'ocSeparatorRectangle' => ['2078443048', 'rectangle-ads manual'],

        // OCセパレーター-rectangle
        'ocSeparatorRectanglePreload' => ['2078443048', 'rectangle-ads preload'],

        // OCセパレーター-横長
        'ocSeparatorWide' => ['1847273098', 'rectangle2-ads manual'],

        // サイトトップ-rectangle
        'siteTopRectangle' => ['4122044659', 'rectangle-ads manual'],

        // サイトトップ2-横長
        'siteTopWide' => ['4015067592', 'rectangle2-ads manual'],

        // サイトセパレーター-レスポンシブ
        'siteSeparatorResponsive' => ['4243068812'],

        // サイトセパレーター-rectangle
        'siteSeparatorRectangle' => ['9793281538', 'rectangle-ads manual'],

        // サイトセパレーター-横長
        'siteSeparatorWide' => ['7150203685', 'rectangle2-ads manual'],

        // おすすめトップ-rectangle
        'recommendTopRectangle' => ['3109180036', 'rectangle-ads manual'],

        // おすすめトップ-rectangle
        'recommendTopRectanglePreload' => ['3109180036', 'rectangle-ads preload'],

        // おすすめトップ2-横長
        'recommendTopWide2' => ['1796098364', 'rectangle2-ads manual'],

        // おすすめセパレーター-横長
        'recommendSeparatorWide' => ['7670645105', 'rectangle2-ads manual'],

        // おすすめセパレーター-レスポンシブ
        'recommendSeparatorResponsive' => ['7064673271'],

        // おすすめセパレーター-Rectangle
        'recommendSeparatorRectangle' => ['8031174545', 'rectangle-ads manual'],

        // おすすめセパレーター-Rectangle
        'recommendSeparatorRectanglePreload' => ['8031174545', 'rectangle-ads preload'],
    ];

    /**
     * @param array $adElement { 0: int, 1: string }|{ 0: int }
     */
    static function output(array $adElement)
    {
        if (count($adElement) === 1) {
            self::responsive($adElement[0], 'responsive-google manual');
        } else {
            self::rectangle($adElement[0], $adElement[1]);
        }
    }

    private static function rectangle(int $adSlot, string $cssClass)
    {
        if (AppConfig::$isStaging && !AppConfig::$isDevlopment) return;

        $adClient = self::AD_CLIENT;
        echo <<<EOT
        <ins class="adsbygoogle {$cssClass}" data-ad-client="{$adClient}" data-ad-slot="{$adSlot}" data-ad-format="rectangle"></ins>
        EOT;
    }

    private static function responsive(int $adSlot, string $cssClass)
    {
        if (AppConfig::$isStaging && !AppConfig::$isDevlopment) return;

        $adClient = self::AD_CLIENT;
        echo <<<EOT
        <ins class="adsbygoogle {$cssClass}" data-ad-client="{$adClient}" data-ad-slot="{$adSlot}" data-ad-format="auto" data-full-width-responsive="true"></ins>
        EOT;
    }

    static function loadAdsTag()
    {
        if (AppConfig::$isStaging || AppConfig::$isDevlopment) return;

        echo <<<EOT
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const num = document.querySelectorAll('ins.manual').length;
                for (let i = 0; i < num; i++) {
                    (adsbygoogle = window.adsbygoogle || []).push({});
                }
            });
        </script>
        EOT;
    }

    static function preloadAdsTag()
    {
        if (AppConfig::$isStaging || AppConfig::$isDevlopment) return;

        echo <<<EOT
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const num = document.querySelectorAll('ins.preload').length;
                for (let i = 0; i < num; i++) {
                    (adsbygoogle = window.adsbygoogle || []).push({});
                }
            });
        </script>
        EOT;
    }

    static function gTag(?string $dataOverlays = null)
    {
        if (AppConfig::$isStaging || AppConfig::$isDevlopment) return;

        $dataOverlaysAttr = $dataOverlays ? ('data-overlays="' . $dataOverlays . '" ') : '';
        $adClient = self::AD_CLIENT;

        echo <<<EOT
        <script async {$dataOverlaysAttr}id="ads-by-google-script" src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={$adClient}" crossorigin="anonymous"></script>
        EOT;
    }
}
