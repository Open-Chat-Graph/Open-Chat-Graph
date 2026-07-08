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
     * @param bool $fullWidthResponsive レスポンシブ枠を端末幅いっぱいに広げるか（既定 true）。
     *                                  リスト内など横インデントされた文脈では false にすると、
     *                                  画面幅への「はみ出し」をやめてコンテナ幅（＝周囲の行と同じ位置）に収まる。
     *                                  固定サイズ（cssClass 有り）の枠には影響しない。
     */
    public static function output(string $slotKey, bool $fullWidthResponsive = true)
    {
        if (AppConfig::$isStaging) return;

        // 設定を取得
        $config = GoogleAdsenseConfig::$googleAdsenseSlots[$slotKey] ?? null;
        if (!$config) return;

        $slotId = $config['slotId'];
        $cssClass = $config['cssClass'] ?? null;

        // レスポンシブ広告（CSSクラスがnull）
        if ($cssClass === null) {
            self::responsive($slotId, 'responsive-google', $fullWidthResponsive);
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

    private static function responsive(string $adSlot, string $cssClass, bool $fullWidthResponsive = true)
    {
        $adClient = GoogleAdsenseConfig::$googleAdsenseClient;
        $fullWidth = $fullWidthResponsive ? 'true' : 'false';

        echo <<<EOT
        <div class="{$cssClass}-parent">
        EOT;

        echo <<<EOT
            <ins class="adsbygoogle manual {$cssClass}" data-ad-client="{$adClient}" data-ad-slot="{$adSlot}" data-ad-format="auto" data-full-width-responsive="{$fullWidth}"></ins>
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

        self::anchorGuard();
    }

    /**
     * オファーウォール（全画面メッセージ）が出た「セッション」では、以降アンカー広告を一切表示しない。
     *
     * 全画面のオファーウォールとアンカー広告（とくに背の高い大型タイプ）が重なるとページが二重に覆われて
     * 操作不能になるため、オファーウォールを一度でも見たセッションでは、そのページも以降のページも
     * アンカー広告（data-anchor-status を持つ ins ＝サイズ・タイプ問わず全部）を display:none にする。
     *
     *  - セッション判定は sessionStorage。オファーウォールを検知したら記録し、以降のページ読み込みでは
     *    最初からアンカーを抑制する（閉じても復帰しない）。
     *  - 判定は全てクライアント JS（MutationObserver を 30ms に集約）で行い、サーバが返す HTML は
     *    全訪問者で同一＝Cloudflare のエッジキャッシュ（Cache Everything）と無衝突。
     *  - 広告ブロック環境ではオファーウォール自体が出ないので何もしない（無害）。
     */
    private static function anchorGuard()
    {
        echo <<<'EOT'
        <style>html.oc-ow-anchor-off ins.adsbygoogle[data-anchor-status]{display:none !important;}</style>
        <script>
            (function () {
                var root = document.documentElement;
                var KEY = 'ocOwSeen'; // このセッションでオファーウォールを見たか
                function suppress() { root.classList.add('oc-ow-anchor-off'); }

                // 既にこのセッションでオファーウォールを見ていれば、最初からアンカーを抑制
                try { if (sessionStorage.getItem(KEY)) suppress(); } catch (e) {}

                // 画面を覆うサイズの Funding Choices オファーウォールが出ているか。
                // 実際のオファーウォールは .fc-monetization-dialog-container / .fc-dialog-overlay を
                // 全画面で描く（本番の実DOMを確認済み。.fc-message-root ではない）。将来のマークアップ
                // 変更に備え旧セレクタもフォールバックとして残す。
                function offerwallShown() {
                    var els = document.querySelectorAll('.fc-monetization-dialog-container, .fc-dialog-overlay, .fc-message-root, iframe[src*="fundingchoicesmessages"]');
                    for (var i = 0; i < els.length; i++) {
                        var r = els[i].getBoundingClientRect();
                        if (r.width >= window.innerWidth * 0.6 && r.height >= window.innerHeight * 0.6) return true;
                    }
                    return false;
                }
                var obs = null, scheduled = false;
                function check() {
                    scheduled = false;
                    if (offerwallShown()) {
                        try { sessionStorage.setItem(KEY, '1'); } catch (e) {}
                        suppress();
                        if (obs) obs.disconnect(); // 以後このセッションは抑制固定なので監視終了
                    }
                }
                function schedule() {
                    if (scheduled) return;
                    scheduled = true;
                    window.setTimeout(check, 30);
                }
                function start() {
                    obs = new MutationObserver(schedule);
                    obs.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style'] });
                    schedule();
                }
                if (document.body) start();
                else document.addEventListener('DOMContentLoaded', start);
            })();
        </script>
        EOT;
    }
}
