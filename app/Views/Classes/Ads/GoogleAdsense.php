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

        // 遅延読み込み(IntersectionObserver)はしない。security.js の広告ブロック検出
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
     * このタグ（/recommend の正規タグ、または /oc の部屋タグ $oc['tag1']）が
     * 「Offerwall 常時表示」対象か。GoogleAdsenseConfig::$offerwallAlwaysOnTags と照合する。
     * tag1 等は DB から HTML エスケープされた形で来る場合があるので、内部で
     * htmlspecialchars_decode してから厳密一致する（recommend_content.php と同じ正規化）。
     * /recommend と /oc がこの1メソッドを共用し、判定がドリフトしないようにする。
     */
    public static function isOfferwallAlwaysOn(?string $tag): bool
    {
        if ($tag === null || $tag === '') return false;
        return in_array(htmlspecialchars_decode($tag), GoogleAdsenseConfig::$offerwallAlwaysOnTags, true);
    }

    /**
     * @param bool $suppressOfferwall true でこのページの Offerwall（全画面メッセージ）のみ常時抑制する。
     *                                同意メッセージ・広告ブロック回復など他のメッセージ表示には影響しない。
     *                                （blog 記事など「初見読者に絶対 Offerwall を出さない」ページ用）
     * @param bool $smartOfferwall    true で Offerwall を訪問者ごとに出し分ける（oc-pdca 2026-06）。
     *                                「初回訪問 × 検索エンジンからの流入」のときだけ Offerwall を抑制して
     *                                SEO ランディングの第一印象を守り、再訪・Direct/SNS・2ページ目以降は通常表示。
     *                                判定はブラウザ JS（document.referrer + localStorage）で行うため、
     *                                サーバが返す HTML は全訪問者で同一＝Cloudflare のエッジキャッシュと無衝突。
     *                                $suppressOfferwall が true の場合はそちらが優先（常時抑制）。
     */
    public static function gTag(?string $dataOverlays = null, bool $suppressOfferwall = false, bool $smartOfferwall = false)
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
        } elseif ($smartOfferwall) {
            // 訪問者ごとの出し分け。判定（初回×検索流入）はクライアント JS の実行時に行うので、
            // キャッシュされる HTML 自体は全訪問者で同一。adsbygoogle.js より前に定義する必要があるため
            // 文字列補間を避けて nowdoc で出力する。https://developers.google.com/funding-choices/fc-api-docs
            echo <<<'EOT'
            <script>
                (function () {
                    window.googlefc = window.googlefc || {};
                    var suppress = false;
                    try {
                        var host = '';
                        try { host = new URL(document.referrer).hostname; } catch (e) {}
                        var fromSearch = /(^|\.)(google|bing|yahoo|duckduckgo|baidu|naver|daum|ecosia|brave|sogou)\./i.test(host);
                        var firstVisit = false;
                        try {
                            firstVisit = !window.localStorage.getItem('ocReturning');
                            if (firstVisit) window.localStorage.setItem('ocReturning', '1');
                        } catch (e) {}
                        // 初回訪問 かつ 検索エンジンからの流入のときだけ Offerwall を抑制する
                        suppress = firstVisit && fromSearch;
                    } catch (e) { suppress = false; }
                    googlefc.controlledMessagingFunction = function (message) {
                        if (suppress) {
                            message.proceed(false, [window.googlefc.MessageTypeEnum.OFFERWALL]);
                        } else {
                            message.proceed(true);
                        }
                    };
                })();
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
