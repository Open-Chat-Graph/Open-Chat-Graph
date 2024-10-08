<!DOCTYPE html>
<html lang="ja">
<?php viewComponent('policy_head', compact('_css', '_meta')) ?>

<body>
    <?php viewComponent('site_header') ?>
    <main>
        <article class="terms">
            <?php \App\Views\Ads\GoogleAdsence::output(\App\Views\Ads\GoogleAdsence::AD_SLOTS['siteTopRectangle']) ?>

            <h1 style="letter-spacing: 0px; overflow: hidden;">プライバシーポリシー</h1>
            <p></p>
            <h2>個人情報の利用目的</h2>
            <p>当サイトでは、メールでのお問い合わせの際に、名前（ニックネーム）、連絡先等の個人情報を送信いただく場合がございます。</p>
            <p>これらの個人情報は質問に対する回答や必要な情報を電子メールなどでご連絡する場合に利用させていただくものであり、個人情報をご提供いただく際の目的以外では利用いたしません。</p>
            <h2>広告について</h2>
            <p>当サイトでは、第三者配信の広告サービス（Google AdSense）を利用しており、ユーザーの興味に応じた商品やサービスの広告を表示するため、クッキー（Cookie）を使用しております。<br>クッキーを使用することで当サイトはお客様のコンピュータを識別できるようになりますが、お客様個人を特定できるものではありません。</p>
            <p>Cookieを無効にする方法やGoogleアドセンスに関する詳細は「<a href="https://policies.google.com/technologies/ads?gl=jp" target="_blank">広告 – ポリシーと規約 – Google</a>」をご確認ください。</p>
            <h2>アクセス解析ツールについて</h2>
            <p>当サイトでは、Googleによるアクセス解析ツール「Googleアナリティクス」を利用しています。</p>
            <p>このGoogleアナリティクスはトラフィックデータの収集のためにCookieを使用しています。このトラフィックデータは匿名で収集されており、個人を特定するものではありません。この機能はCookieを無効にすることで収集を拒否することが出来ますので、お使いのブラウザの設定をご確認ください。この規約に関しての詳細は「<a href="https://marketingplatform.google.com/about/analytics/terms/jp/" target="_blank">Googleアナリティクスサービス利用規約</a>」や「<a href="https://policies.google.com/technologies/ads?hl=ja" target="_blank">Googleポリシーと規約ページ</a>」をご確認ください。</p>
            <h2>免責事項</h2>
            <p>当サイトからリンクやバナーなどによって他のサイトに移動された場合、移動先サイトで提供される情報、サービス等について一切の責任を負いません。</p>
            <p>当サイトのコンテンツ・情報につきまして、可能な限り正確な情報を掲載するよう努めておりますが、誤情報が入り込んだり、情報が古くなっていることもございます。</p>
            <p>当サイトに掲載された内容によって生じた損害等の一切の責任を負いかねますのでご了承ください。</p>
            <h2>著作権について</h2>
            <p>当サイトで掲載している画像の著作権・肖像権等は各権利所有者に帰属致します。権利を侵害する目的ではございません。ページの内容や掲載画像等に問題がございましたら、各権利所有者様本人が<a href="mailto:support@openchat-review.me">お問い合わせ窓口のメール</a>でご連絡ください。できるだけ迅速に対応させて頂きます。</p>
            <h2>プライバシーポリシーの変更について</h2>
            <p>当サイトは、個人情報に関して適用される日本の法令を遵守するとともに、本ポリシーの内容を適宜見直しその改善に努めます。</p>
            <p>修正された最新のプライバシーポリシーは常に本ページにて開示されます。</p>
            <h2>メールでのお問い合わせ先</h2>
            <p>オプチャグラフお問い合わせ窓口: <a href="mailto:support@openchat-review.me">support@openchat-review.me</a></p>
        </article>
    </main>
    <footer class="footer-elem-outer">
        <?php viewComponent('footer_inner') ?>
    </footer>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const num = document.querySelectorAll('ins').length;
            for (let i = 0; i < num; i++) {
                (adsbygoogle = window.adsbygoogle || []).push({});
            }
        });
    </script>
    <?php echo $_breadcrumbsShema ?>
</body>

</html>