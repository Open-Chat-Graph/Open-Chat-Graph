<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php

use App\Views\Content\LabsNews;

viewComponent('policy_head', compact('_css', '_meta')) ?>

<body>
    <div class="body">
        <?php viewComponent('site_header') ?>
        <main style="overflow: hidden;">
            <article class="terms">
                <h1 style="letter-spacing: 0px;">
                    <svg style="color: #111; fill: currentColor; display: inline-block; margin-right: 4px; margin-bottom: -4px; margin-left: -12px;" focusable="false" height="24px" viewBox="0 -960 960 960" width="24px">
                        <path d="M209-120q-42 0-70.5-28.5T110-217q0-14 3-25.5t9-21.5l228-341q10-14 15-31t5-34v-110h-20q-13 0-21.5-8.5T320-810q0-13 8.5-21.5T350-840h260q13 0 21.5 8.5T640-810q0 13-8.5 21.5T610-780h-20v110q0 17 5 34t15 31l227 341q6 9 9.5 20.5T850-217q0 41-28 69t-69 28H209Zm221-660v110q0 26-7.5 50.5T401-573L276-385q-6 8-8.5 16t-2.5 16q0 23 17 39.5t42 16.5q28 0 56-12t80-47q69-45 103.5-62.5T633-443q4-1 5.5-4.5t-.5-7.5l-78-117q-15-21-22.5-46t-7.5-52v-110H430Z"></path>
                    </svg><span style="line-height: 2;">分析Labs</span>
                </h1>
                <p>試験運用版の分析機能をお試しいただけます。</p>
                <?php viewComponent('update_news', ['_news' => array_reverse(LabsNews::getNews())]) ?>

                <h2>ライブトーク利用時間分析ツール</h2>
                <a href="<?php echo url('labs/live') ?>" aria-label="ライブトーク利用時間分析ツール">
                    <img src="/labs-img/livegraph.webp" alt="ライブトーク利用時間分析ツール" />
                </a>
                <p>ライブトークの利用時間を分析するツールです。トーク履歴から利用時間推移をグラフで表示します。</p>
                <a class="top-ranking-readMore unset" style="margin:0" href="<?php echo url('labs/tags') ?>">
                    <span class="ranking-readMore">ライブトーク利用時間分析ツールを開く<span class="small"></span>
                </a>
            </article>
            <?php \App\Views\Ads\GoogleAdsense::output(\App\Views\Ads\GoogleAdsense::AD_SLOTS['siteTopRectangle']) ?>
            <article class="terms" style="opacity: .5;">
                <h2>(公開中止)タグで見るトレンド動向</h2>
                <!-- <a href="<?php echo url('labs/tags') ?>" aria-label="タグで見るトレンド動向"> -->
                <img src="/labs-img/tags.webp" alt="タグで見るトレンド動向" />
                <!-- </a> -->
                <p>タグによる分類を用いたトレンド分析では、グループごとに増減数や合計人数の集計を行います。これにより、各トピックの人気度やその変動を捉えることができます。</p>
                <!-- <a class="top-ranking-readMore unset" style="margin:0" href="<?php echo url('labs/tags') ?>">
                    <span class="ranking-readMore">タグで見るトレンド動向を開く<span class="small"></span>
                </a> -->

                <h2>(公開中止)オプチャ公式ランキング掲載の分析</h2>
                <!-- <a href="<?php echo url('labs/publication-analytics') ?>" aria-label="オプチャ公式ランキング掲載の分析"> -->
                <img src="/labs-img/ranking.webp" alt="オプチャ公式ランキング掲載の分析" />
                <!-- </a> -->
                <p>この分析機能は、ルームの集客にとって重要な公式ランキングの可視性の傾向を分析することができます。</p>
                <p>全オープンチャットの状況を追跡し、ランキングへの掲載状態（掲載・未掲載）、および内容変更の履歴を記録しています。
                    <br>特定のルームがいつからランキングに未掲載となり、またいつ再掲載されたかを一目で確認できます。
                    <br>これにより、ルーム内容の変更後に生じる掲載状態の変動（例えば、検索からの除外など）を把握することができます
                </p>
                <!-- <a class="top-ranking-readMore unset" style="margin:0" href="<?php echo url('labs/publication-analytics') ?>">
                    <span class="ranking-readMore">オプチャ公式ランキング掲載の分析を開く</span>
                </a> -->
            </article>
        </main>

        <?php viewComponent('footer_inner') ?>
    </div>

    <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
    <?php echo $_breadcrumbsShema ?>
</body>

</html>