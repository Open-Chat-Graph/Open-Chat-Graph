<?php

namespace App\Views\Ads;

use App\Config\AppConfig;
use App\Config\GoogleAdsenseConfig;

class GoogleAdsense
{
    /**
     * 広告を出力
     *
     * @param string $slotKey スロット識別子（例: 'ocTopRectangle'）
     */
    public static function output(string $slotKey)
    {
        if (AppConfig::$isStaging) return;

        // 設定を取得
        $config = GoogleAdsenseConfig::$googleAdsenseSlots[$slotKey] ?? null;
        if (!$config) return;

        $slotId = $config['slotId'];
        $cssClass = $config['cssClass'] ?? null;

        // レスポンシブ広告（CSSクラスがnull）
        if ($cssClass === null) {
            self::responsive($slotId, 'responsive-google');
        } else {
            self::rectangle($slotId, $cssClass);
        }
    }

    private static function rectangle(string $adSlot, string $cssClass)
    {
        $adClient = GoogleAdsenseConfig::$googleAdsenseClient;

        echo <<<EOT
        <div class="{$cssClass}-parent">
        EOT;

        echo <<<EOT
            <ins class="adsbygoogle manual {$cssClass}" data-ad-client="{$adClient}" data-ad-slot="{$adSlot}" data-full-width-responsive="false"></ins>
        EOT;

        echo <<<EOT
        </div>
        EOT;
    }

    private static function responsive(string $adSlot, string $cssClass)
    {
        $adClient = GoogleAdsenseConfig::$googleAdsenseClient;

        echo <<<EOT
        <div class="{$cssClass}-parent">
        EOT;

        echo <<<EOT
            <ins class="adsbygoogle manual {$cssClass}" data-ad-client="{$adClient}" data-ad-slot="{$adSlot}" data-ad-format="auto" data-full-width-responsive="false"></ins>
        EOT;

        echo <<<EOT
        </div>
        EOT;
    }

    public static function loadAdsTag()
    {
        if (AppConfig::$isStaging || AppConfig::$isDevlopment) return;

        // 遅延読み込み(IntersectionObserver)はしない。広告ブロック検出(ad_guard)
        // （window load 時の未処理チェック・10秒間の1px潰し監視）は「広告がページ表示時に
        // 読み込まれている」前提のため、遅延させると検出が成立しなくなる
        echo <<<EOT
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('ins.manual').forEach(function() {
                    (adsbygoogle = window.adsbygoogle || []).push({});
                });
            });
        </script>
        EOT;
    }

    /**
     * @param bool $suppressOfferwall true でこのページの Offerwall（全画面メッセージ）のみ常時抑制する。
     *                                同意メッセージ・広告ブロック回復など他のメッセージ表示には影響しない。
     *                                （blog 記事など「初見読者に絶対 Offerwall を出さない」ページ用）
     */
    public static function gTag(?string $dataOverlays = null, bool $suppressOfferwall = false)
    {
        if (AppConfig::$isStaging || AppConfig::$isDevlopment) return;

        if ($suppressOfferwall) {
            // adsbygoogle.js のロードより前に定義される必要があるため、スクリプトタグの直前で出力する。
            // https://developers.google.com/funding-choices/fc-api-docs
            echo <<<EOT
            <script>
                window.googlefc = window.googlefc || {};
                googlefc.controlledMessagingFunction = function (message) {
                    message.proceed(false, [window.googlefc.MessageTypeEnum.OFFERWALL]);
                };
            </script>
            EOT;
        }

        $dataOverlaysAttr = $dataOverlays ? ('data-overlays="' . $dataOverlays . '" ') : '';
        $adClient = GoogleAdsenseConfig::$googleAdsenseClient;

        echo <<<EOT
        <script async {$dataOverlaysAttr}id="ads-by-google-script" src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={$adClient}" crossorigin="anonymous"></script>
        EOT;
    }
}
