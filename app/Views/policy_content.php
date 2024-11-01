<!DOCTYPE html>
<html lang="ja">
<?php viewComponent('policy_head', compact('_css', '_meta')) ?>

<body>
    <script type="application/json" id="comment-app-init-dto">
        <?php echo json_encode(['openChatId' => 0, 'baseUrl' => url()], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
    </script>
    <script type="module" crossorigin src="/<?php echo getFilePath('js/comment', 'index-*.js') ?>"></script>
    <?php viewComponent('site_header') ?>
    <main style="overflow: hidden;">
        <article class="terms">
            <?php \App\Views\Ads\GoogleAdsence::output(\App\Views\Ads\GoogleAdsence::AD_SLOTS['siteTopRectangle']) ?>

            <h1 style="letter-spacing: 0px;">オプチャグラフについて</h1>
            <p>オプチャグラフはユーザーがオープンチャットを見つけて、成長傾向をグラフやランキングで比較できるWEBサイトです。</p>
            <p>LINE非公式の<a href="https://github.com/pika-0203/Open-Chat-Graph" target="_blank">オープンソースプロジェクト</a>により運営されています。 </p>
            <div style="margin: 2rem 0;">
                <?php viewComponent('update_news', compact('_news')) ?>
            </div>
            <h2>サイトの目的</h2>
            <p>・ユーザーがオープンチャットを見つけて参加する機会を作る</p>
            <p>・オープンチャットの管理者が成長傾向を把握し、比較できる事で運営に役立つ</p>

            <h2>オープンチャットの情報を掲載する仕組み</h2>
            <p>
                オプチャグラフは「<a href="https://openchat.line.me/jp" rel="external" target="_blank">LINEオープンチャット公式サイト</a>」のデータを基に、グラフやランキングを作成して掲載しています。
            </p>
            <p>
                最新データを表示するため、オプチャグラフの<a href="https://webtan.impress.co.jp/g/%E3%82%AF%E3%83%AD%E3%83%BC%E3%83%A9%E3%83%BC" target="_blank">クローラー（巡回プログラム）</a>が公式サイトを定期巡回して、オープンチャットのデータをインデックス（記録）しています。
            </p>
            <p>
                <b>データの取得は公式サイトのみから行います。LINEアプリ本体に係るデータを取得することはありません。</b>
            </p>
            <section style="margin: 2rem 0;">
                <h3 style="font-size: 14px;">オプチャグラフに掲載される条件</h3>
                <p>
                    オプチャグラフのクローラーは<a href="https://openchat.line.me/jp/explore?sort=RANKING" rel="external" target="_blank">公式サイトのランキング</a>から新しいルームを見つけて登録します。<b>ランキングに掲載されていないルームは登録されません。</b>
                </p>
                <p>
                    一度オプチャグラフに登録されたルームはランキング掲載の有無に関わらず、引き続きオプチャグラフに掲載されます。
                </p>
                <p>
                    公式サイトにて掲載が終了・削除されたルームは、オプチャグラフから削除されます。
                </p>
                <p>
                    ランキングに未掲載（開設して間もないなど）のルームは、公式サイトに掲載されている場合に限り、手動でオプチャグラフに登録できます。
                </p>
                <p>
                    <a href="<?php echo url('oc') ?>">オープンチャットを手動で登録する</a>
                </p>
                <p>
                    <a href="<?php echo url('recently-registered') ?>">最近登録されたオープンチャット</a>
                </p>
            </section>
            <section style="margin: 2rem 0;">
                <h3 style="font-size: 14px;">公式サイトでの掲載条件</h3>
                <p>
                    オープンチャットの検索を許可しているなどの条件において、開設したルームが公式サイトに掲載されます。
                </p>
                <p>
                    検索をOFFに設定変更した場合、公式サイトの掲載が削除され、オプチャグラフからも削除されます。
                </p>
                <p>
                    参考ページ: <a href="https://openchat-jp.line.me/other/notice_webmain_3gf87gs1" target="_blank">Webブラウザ版メイン画面公開と検索エンジン対応のお知らせ | LINEオープンチャット</a>
                </p>
            </section>
            <section style="margin: 2rem 0;">
                <h3 style="font-size: 14px;">情報更新のスケジュール</h3>
                <p>
                    オプチャグラフのクローラーは公式サイトを定期巡回してルームのタイトル、説明文、画像、人数統計、ランキング履歴などを更新します。
                </p>
                <ul style="font-size: 14px; line-height: 2;">
                    <li>ランキング掲載中のルーム: 1時間毎（毎時30分頃）</li>
                    <li>ランキング未掲載のルーム: 1日毎 （23:30〜0:30頃）</li>
                    <li>ランキング未掲載かつ1週間以上メンバー数に変動がないルーム: 1週間毎</li>
                </ul>
            </section>
            <section style="margin: 2rem 0;">
                <h3 style="font-size: 14px;">キーワード検索機能について</h3>
                <p>
                    オプチャグラフが提供するキーワード検索機能は公式サイトからインデックスした情報に基づいています。Google・Yahoo・Bingなどの検索サイトが表示する検索結果と同様の内容です。
                </p>
                <p>
                    LINE公式のキーワード検索機能とオプチャグラフはリンクしておらず、異なるものです。オプチャグラフが公式の検索機能からデータを取得することはありません。
                </p>
                <p>
                    <b>オプチャグラフはLINE公式の検索機能について関与していません。公式の検索機能にルームが表示されない理由を調べることはできません。</b>
                </p>
            </section>
            <section style="margin: 2rem 0;">
                <h3 style="font-size: 14px;">ランキングの順位グラフについて</h3>
                <p>
                    オプチャグラフのクローラーは公式サイトのランキング順位を1時間毎に記録します。ルームの並び順から順位を数えて算出しています。
                </p>
                <p>
                    ランキングに掲載がなかったルームは「圏外」として記録されます。ルーム管理者によるルーム情報（タイトル、説明文、画像）の更新後や、サーバーエラーなどでも圏外になる場合があります。
                </p>
                <p>
                    <b>オプチャグラフはLINE公式のランキング掲載基準について関与していません。ルームの審査基準等を調べるためのツールではありません。</b>
                </p>
            </section>
            <h2>オプチャグラフ公開の経緯</h2>
            <p>
                オプチャグラフの公開が可能になった経緯として、オプチャ公式による検索エンジンへの対応が始まった事があげられます。
            </p>
            <p>
                オープンチャットのサービス開始当初、オープンチャットを検索したり、ランキングを見る機能はLINEアプリ内限定で提供されていました。
            </p>
            <p>
                しかし、2023年10月頃に<a href="https://openchat.line.me/jp" target="_blank">「WEBブラウザ版メイン画面」（公式サイト）</a>が公開された事により、LINEアプリ外のブラウザからオープンチャットを検索したり、ランキングを見ることが可能になりました。
            </p>
            <p>
                参加経路の拡大を図るため、「WEBブラウザ版メイン画面」に掲載されているオープンチャットを検索エンジン（Googleなど）の検索結果に表示させるというものです。
            </p>
            <p>
                <a href="https://ja.wikipedia.org/wiki/%E6%A4%9C%E7%B4%A2%E3%82%A8%E3%83%B3%E3%82%B8%E3%83%B3%E6%9C%80%E9%81%A9%E5%8C%96" target="_blank">SEO</a>と言われるマーケティングの一環により、検索エンジンに積極的な掲載を図りユーザーの認知を増やすことを目的とした媒体であると考えられます。
            </p>
            <p>
            </p>
            <p>
                <b>オプチャグラフは「WEBブラウザ版メイン画面」の分析データを掲載し、参加経路拡大に寄与するために開発されたオープンチャット専用の検索エンジンです。</b>
            </p>
            <p>
                オプチャグラフのクローラーが公式サイトのデータをクローリングし、Google・Bingなどの検索エンジンと同様の一般的なルールに基づいてデータを公開しています。
            </p>
            <p>
                オープンチャットのデータを不正に公開するものではなく、適切な範囲内で健全な情報共有を行うWEBサイトです。
            </p>
            <p>
                参考ページ: <a href="https://openchat-jp.line.me/other/notice_webmain_3gf87gs1" target="_blank">Webブラウザ版メイン画面公開と検索エンジン対応のお知らせ | LINEオープンチャット</a>
            </p>
            <br>
            <p>
                オープンチャットの参加経路拡大に寄与するため、オプチャグラフのページもSEOを考慮した設計となっています。
                <span id="comments" aria-hidden="true"></span>
            </p>

            <h2 style="margin-bottom: 2rem;">オプチャグラフに関する情報共有・コメント</h2>
            <div style="min-height: 400px;">
                <div id="comment-root"></div>
            </div>

            <h2>メールでのお問い合わせ先</h2>
            <p>オプチャグラフお問い合わせ窓口: <a href="mailto:support@openchat-review.me">support@openchat-review.me</a></p>
        </article>
    </main>
    <footer class="footer-elem-outer">
        <?php viewComponent('footer_inner') ?>
    </footer>
    <?php \App\Views\Ads\GoogleAdsence::loadAdsTag() ?>
    <?php echo $_breadcrumbsShema ?>
</body>

</html>