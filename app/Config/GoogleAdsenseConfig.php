<?php

namespace App\Config;

class GoogleAdsenseConfig
{
    // Google AdSense設定
    static string $googleAdsenseClient = 'ca-pub-2330982526015125'; // 広告クライアントID

    /**
     * Offerwall（全画面メッセージ）を「常に表示」にする /recommend タグ（ja の正規タグ名で一致判定）。
     *
     * 通常の /recommend ページは GoogleAdsense::gTag(smartOfferwall: true) でクライアント側の出し分けを行い、
     * 「初回 × 検索エンジン流入」の初見訪問にだけ Offerwall を抑制して SEO ランディングの第一印象を守る。
     * 一方ここに挙げたタグは、アダルト/大人系で（a）Offerwall の違和感が小さく（b）第一印象を守る必要性が低く
     * （c）高収益なので、出し分けの例外として「初見でも常時表示」する。
     *
     * 判定は recommend_content.php で $_tagIndex（= 正規タグ = URLパス = Cloudflare のキャッシュキー）に対して行うため、
     * 訪問者ごとに HTML が変わらず、エッジキャッシュと無衝突。タグを増減したいときはこの配列を編集するだけ。
     */
    static array $offerwallAlwaysOnTags = [
        '下ネタ',
        '大人',
        'その先',
        '40代',
        '50代',
        '60代',
        '70代',
        '全国 雑談', // 半角スペース（U+0020）。ja.json の正規タグと完全一致させること
    ];

    /**
     * Google AdSense広告スロット設定
     *
     * キー: スロット識別子（文字列）
     * 値: ['slotId' => 広告スロットID, 'cssClass' => CSSクラス名|null]
     *
     * 新しいスロットを追加する場合は、1行追加するだけ：
     * 'newSlotKey' => ['slotId' => '1234567890', 'cssClass' => 'rectangle3-ads'],
     */
    static array $googleAdsenseSlots = [
        // OCトップ-horizontal
        'ocTopHorizontal' => ['slotId' => '9641198670', 'cssClass' => 'horizontal-ads'],
        // OCトップ-rectangle
        'ocTopRectangle' => ['slotId' => '8037531176', 'cssClass' => 'rectangle3-ads'],
        // OCトップ2-rectangle
        'ocTop2Rectangle' => ['slotId' => '4585711910', 'cssClass' => 'rectangle3-ads'],
        // OC-third-rectangle
        'ocThirdRectangle' => ['slotId' => '8325497013', 'cssClass' => 'rectangle3-ads'],
        // OCトップ2-横長
        'ocTopWide2' => ['slotId' => '6469006397', 'cssClass' => 'rectangle2-ads'],
        // OC-third-横長
        'ocThirdWide' => ['slotId' => '4386252007', 'cssClass' => 'rectangle2-ads'],
        // OCセパレーター-レスポンシブ
        'ocSeparatorResponsive' => ['slotId' => '2542775305', 'cssClass' => null],
        // OCセパレーター-rectangle
        'ocSeparatorRectangle' => ['slotId' => '2078443048', 'cssClass' => 'rectangle3-ads'],
        // OC-リスト-bottom-横長
        'ocListBottomWide' => ['slotId' => '9996104663', 'cssClass' => 'rectangle2-ads'],
        // OC-bottom-wide
        'ocBottomWide' => ['slotId' => '9240027393', 'cssClass' => 'rectangle2-ads'],
        // OC-footer-rectangle
        'ocFooterRectangle' => ['slotId' => '2217617182', 'cssClass' => 'rectangle3-ads'],
        // OCセパレーター-横長
        'ocSeparatorWide' => ['slotId' => '1847273098', 'cssClass' => 'rectangle2-ads'],
        // サイトトップ-rectangle
        'siteTopRectangle' => ['slotId' => '4122044659', 'cssClass' => 'rectangle3-ads'],
        // サイトトップ2-横長
        'siteTopWide' => ['slotId' => '4015067592', 'cssClass' => 'horizontal-ads'],
        // サイトセパレーター-レスポンシブ
        'siteSeparatorResponsive' => ['slotId' => '4243068812', 'cssClass' => null],
        // サイトセパレーター-rectangle
        'siteSeparatorRectangle' => ['slotId' => '9793281538', 'cssClass' => 'rectangle-ads'],
        // サイトセパレーター-横長
        'siteSeparatorWide' => ['slotId' => '7150203685', 'cssClass' => 'rectangle2-ads'],
        // サイト-bottom-wide
        'siteBottomWide' => ['slotId' => '8637392164', 'cssClass' => 'rectangle2-ads'],
        // おすすめトップ-rectangle
        'recommendTopRectangle' => ['slotId' => '3109180036', 'cssClass' => 'rectangle3-ads'],
        // おすすめトップ-recommendTopHorizontal
        'recommendTopHorizontal' => ['slotId' => '5472515659', 'cssClass' => 'horizontal-ads'],
        // おすすめ-third-rectangle
        'recommendThirdRectangle' => ['slotId' => '3035874831', 'cssClass' => 'rectangle3-ads'],
        // おすすめトップ-横長
        'recommendTopWide' => ['slotId' => '9253567316', 'cssClass' => 'rectangle2-ads'],
        // おすすめトップ2-横長
        'recommendTopWide2' => ['slotId' => '1796098364', 'cssClass' => 'rectangle2-ads'],
        // おすすめ-third-横長
        'recommendThirdWide' => ['slotId' => '4136934018', 'cssClass' => 'rectangle2-ads'],
        // おすすめセパレーター-横長
        'recommendSeparatorWide' => ['slotId' => '7670645105', 'cssClass' => 'rectangle2-ads'],
        // おすすめ-リスト-bottom-横長
        'recommendListBottomWide' => ['slotId' => '3676170522', 'cssClass' => 'rectangle2-ads'],
        // おすすめセパレーター-レスポンシブ
        'recommendSeparatorResponsive' => ['slotId' => '7064673271', 'cssClass' => null],
        // おすすめセパレーター-Rectangle
        'recommendSeparatorRectangle' => ['slotId' => '8031174545', 'cssClass' => 'rectangle3-ads'],
        // おすすめ-footer-rectangle
        'recommendFooterRectangle' => ['slotId' => '1260592882', 'cssClass' => 'rectangle-ads'],
        // おすすめ-bottom-wide
        'recommendBottomWide' => ['slotId' => '7561513017', 'cssClass' => 'rectangle2-ads'],
        // コメントタイムライントップ-rectangle
        'recentCommentTopRectangle' => ['slotId' => '4440788981', 'cssClass' => 'rectangle3-ads'],
        // コメントタイムラインセパレーター-レスポンシブ
        'recentCommentSeparatorResponsive' => ['slotId' => '4852423347', 'cssClass' => null],
    ];
}
