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
     * @param bool $forceShow 強制表示フラグ
     */

    public static function output(string $slotKey, bool $forceShow = false)
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

        echo <<<EOT
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var ads = Array.prototype.slice.call(document.querySelectorAll('ins.manual'));
                if (!ads.length) return;
                var push = function() { (adsbygoogle = window.adsbygoogle || []).push({}); };

                // IntersectionObserver 非対応環境は従来どおり一括読み込み（フォールバック）
                if (!('IntersectionObserver' in window)) {
                    ads.forEach(push);
                    return;
                }

                // 各広告がビューポート手前 600px に入って初めて読み込む。
                // 下部広告がユーザー到達前に「表示済み」化するのを防ぎ視認率(viewability)を上げる。
                // push() は DOM 順で最も先頭の未読み込み ins を埋めるため、上→下のスクロールで順に充填される。
                var io = new IntersectionObserver(function(entries, obs) {
                    entries.forEach(function(entry) {
                        if (!entry.isIntersecting) return;
                        push();
                        obs.unobserve(entry.target);
                    });
                }, { rootMargin: '600px 0px' });
                ads.forEach(function(ad) { io.observe(ad); });
            });
        </script>
        EOT;
    }

    public static function gTag(?string $dataOverlays = null)
    {
        if (AppConfig::$isStaging || AppConfig::$isDevlopment) return;

        $dataOverlaysAttr = $dataOverlays ? ('data-overlays="' . $dataOverlays . '" ') : '';
        $adClient = GoogleAdsenseConfig::$googleAdsenseClient;

        echo <<<EOT
        <script async {$dataOverlaysAttr}id="ads-by-google-script" src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={$adClient}" crossorigin="anonymous"></script>
        EOT;
    }
}
