<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php viewComponent('policy_head', compact('_css', '_meta')) ?>

<body>
    <?php \App\Views\Ads\GoogleAdsense::gTag() ?>
    <div class="body">
        <?php viewComponent('site_header') ?>
        <main style="overflow: hidden;">
            <article class="terms">
                <h1 class="labs-h1">
                    <svg class="labs-h1-icon" focusable="false" viewBox="0 -960 960 960" aria-hidden="true">
                        <path d="M209-120q-42 0-70.5-28.5T110-217q0-14 3-25.5t9-21.5l228-341q10-14 15-31t5-34v-110h-20q-13 0-21.5-8.5T320-810q0-13 8.5-21.5T350-840h260q13 0 21.5 8.5T640-810q0 13-8.5 21.5T610-780h-20v110q0 17 5 34t15 31l227 341q6 9 9.5 20.5T850-217q0 41-28 69t-69 28H209Zm221-660v110q0 26-7.5 50.5T401-573L276-385q-6 8-8.5 16t-2.5 16q0 23 17 39.5t42 16.5q28 0 56-12t80-47q69-45 103.5-62.5T633-443q4-1 5.5-4.5t-.5-7.5l-78-117q-15-21-22.5-46t-7.5-52v-110H430Z"></path>
                    </svg>分析Labs
                </h1>
                <p class="labs-intro">試験運用版の分析機能をお試しいただけます。</p>

                <div class="labs-list">
                    <a class="labs-card" href="<?php echo url('labs/growth') ?>" aria-label="詳細成長分析を開く">
                        <img src="/labs-img/growth.webp" alt="詳細成長分析" width="1200" height="750">
                        <div class="labs-card-body">
                            <h2>詳細成長分析</h2>
                            <p>月間・年間・任意期間の増加数や増加率、さらに数年かけて“じわじわ”伸び続けている部屋を、カテゴリ・キーワードで絞り込んで探せます。オープンチャットのデータ分析者がしたい方向けの本格機能です。</p>
                            <span class="labs-card-open">開く</span>
                        </div>
                    </a>

                    <a class="labs-card" href="<?php echo url('labs/live') ?>" aria-label="ライブトーク利用時間分析ツールを開く">
                        <img src="/labs-img/livegraph.webp" alt="ライブトーク利用時間分析ツール" width="643" height="610">
                        <div class="labs-card-body">
                            <h2>ライブトーク利用時間分析ツール</h2>
                            <p>トーク履歴から、ライブトークの利用時間の推移をグラフで表示します。</p>
                            <span class="labs-card-open">開く</span>
                        </div>
                    </a>

                    <a class="labs-card" href="<?php echo url('labs/all-room-stats') ?>" aria-label="オープンチャット全体統計を開く">
                        <img src="/labs-img/all_room_stats.webp" alt="オープンチャット全体統計" width="2400" height="2505">
                        <div class="labs-card-body">
                            <h2>オープンチャット全体統計</h2>
                            <p>登録されている全オープンチャットの統計データ。総ルーム数・総参加者数・カテゴリー別の内訳を一覧表示します。</p>
                            <span class="labs-card-open">開く</span>
                        </div>
                    </a>

                    <a class="labs-card" href="<?php echo url('labs/publication-analytics') ?>" aria-label="オプチャ公式ランキング掲載の分析を開く">
                        <img src="/labs-img/ranking.webp" alt="オプチャ公式ランキング掲載の分析" width="1192" height="796">
                        <div class="labs-card-body">
                            <h2>オプチャ公式ランキング掲載の分析</h2>
                            <p>集客に重要な公式ランキングの掲載状態と内容変更の履歴を追跡し、いつ未掲載・再掲載になったかを確認できます。</p>
                            <span class="labs-card-open">開く</span>
                        </div>
                    </a>
                </div>
            </article>
        </main>

        <?php viewComponent('footer_inner') ?>
    </div>

    <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
    <?php echo $_breadcrumbsShema ?>
</body>

</html>
