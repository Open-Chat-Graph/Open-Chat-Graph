<?php

namespace App\Views\Ads;

use App\Config\AppConfig;

class GoogleAdsence
{
    const AD_CLIENT = 'ca-pub-2330982526015125';

    const AD_SLOTS = [
        // OCトップ-horizontal
        'ocTopHorizontal' => ['9641198670', 'horizontal-ads'],

        // OCトップ2-rectangle
        'ocTop2Rectangle' => ['4585711910', 'rectangle-ads'],

        // OC-third-rectangle
        'ocThirdRectangle' => ['8325497013', 'rectangle4-ads'],

        // OCトップ2-横長
        'ocTopWide2' => ['6469006397', 'rectangle2-ads'],

        // OCトップ2-横長
        'ocTopWide2' => ['6469006397', 'rectangle2-ads'],

        // OC-third-横長
        'ocThirdWide' => ['4386252007', 'rectangle2-ads'],

        // OCセパレーター-レスポンシブ
        'ocSeparatorResponsive' => ['2542775305'],

        // OCセパレーター-rectangle
        'ocSeparatorRectangle' => ['2078443048', 'rectangle4-ads'],

        // OC-リスト-bottom-横長
        'ocListBottomWide' => ['9996104663', 'rectangle2-ads'],

        // OC-bottom-wide
        'ocBottomWide' => ['9240027393', 'rectangle2-ads'],

        // OC-footer-rectangle
        'ocFooterRectangle' => ['2217617182', 'rectangle4-ads'],

        // OCセパレーター-横長
        'ocSeparatorWide' => ['1847273098', 'rectangle2-ads'],

        // サイトトップ-rectangle
        'siteTopRectangle' => ['4122044659', 'rectangle-ads'],

        // サイトトップ2-横長
        'siteTopWide' => ['4015067592', 'horizontal-ads'],

        // サイトセパレーター-レスポンシブ
        'siteSeparatorResponsive' => ['4243068812'],

        // サイトセパレーター-rectangle
        'siteSeparatorRectangle' => ['9793281538', 'rectangle-ads'],

        // サイトセパレーター-横長
        'siteSeparatorWide' => ['7150203685', 'rectangle2-ads'],

        // サイト-bottom-wide
        'siteBottomWide' => ['8637392164', 'rectangle2-ads'],

        // おすすめトップ-rectangle
        'recommendTopRectangle' => ['3109180036', 'rectangle-ads'],

        // おすすめトップ-rectangle
        'recommendTopHorizontal' => ['5472515659', 'horizontal-ads'],

        // おすすめ-third-rectangle
        'recommendThirdRectangle' => ['3035874831', 'rectangle4-ads'],

        // おすすめトップ-横長
        'recommendTopWide' => ['9253567316', 'rectangle2-ads'],

        // おすすめトップ2-横長
        'recommendTopWide2' => ['1796098364', 'rectangle2-ads'],

        // おすすめ-third-横長
        'recommendThirdWide' => ['4136934018', 'rectangle2-ads'],

        // おすすめセパレーター-横長
        'recommendSeparatorWide' => ['7670645105', 'rectangle2-ads'],

        // おすすめ-リスト-bottom-横長
        'recommendListBottomWide' => ['3676170522', 'rectangle2-ads'],

        // おすすめセパレーター-レスポンシブ
        'recommendSeparatorResponsive' => ['7064673271'],

        // おすすめセパレーター-Rectangle
        'recommendSeparatorRectangle' => ['8031174545', 'rectangle4-ads'],

        // おすすめ-footer-rectangle
        'recommendFooterRectangle' => ['1260592882', 'rectangle-ads'],

        // おすすめ-bottom-wide
        'recommendBottomWide' => ['7561513017', 'rectangle2-ads'],

        // コメントタイムライントップ-rectangle
        'recentCommentTopRectangle' => ['4440788981', 'rectangle2-ads'],

        // コメントタイムライン-bottom-横長
        'recentCommentBottomWide' => ['4852423347', 'rectangle2-ads'],
    ];

    /**
     * @param array $adElement { 0: int, 1: string }|{ 0: int }
     */
    static function output(array $adElement)
    {
        if (AppConfig::$isStaging || AppConfig::$disableAds) return;

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
        <div style="padding: 12px 1rem; box-sizing: border-box;">
            <ins class="adsbygoogle manual {$cssClass}" data-ad-client="{$adClient}" data-ad-slot="{$adSlot}" data-ad-format="auto" data-full-width-responsive="false"></ins>
        </div>
        EOT;
    }

    private static function responsive(int $adSlot, string $cssClass)
    {
        $adClient = self::AD_CLIENT;
        echo <<<EOT
        <div style=""padding 12px 1rem; box-sizing: border-box;">
            <ins class="adsbygoogle manual {$cssClass}" data-ad-client="{$adClient}" data-ad-slot="{$adSlot}" data-ad-format="auto" data-full-width-responsive="false"></ins>
        </div>
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

    static function gTag(?string $dataOverlays = null)
    {
        if (AppConfig::$isStaging || AppConfig::$isDevlopment || AppConfig::$disableAds) return;

        $dataOverlaysAttr = $dataOverlays ? ('data-overlays="' . $dataOverlays . '" ') : '';
        $adClient = self::AD_CLIENT;

        echo <<<EOT
        <script async {$dataOverlaysAttr}id="ads-by-google-script" src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={$adClient}" crossorigin="anonymous"></script>
        EOT;
    }
}
