<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php viewComponent('head', compact('_css', '_meta') + ['disableGAd' => false]) ?>

<body class="body">
    <style>
        .list-title {
            color: #111;
            all: unset;
            font-size: 20px;
            font-weight: bold;
        }
    </style>
    <?php viewComponent('site_header') ?>
    <main style="padding: 0 1rem;" style="overflow: hidden;">
        <?php \App\Views\Ads\GoogleAdsense::output(\App\Views\Ads\GoogleAdsense::AD_SLOTS['siteTopRectangle']) ?>

        <h2>オプチャグラフにオープンチャットを手動で登録する</h2>
        <p>
            公式ランキングに掲載されているオープンチャットはオプチャグラフに自動登録されます。
        </p>
        <section>
            <p>
                <b>
                    以下の手順は公式ランキングに未掲載のオープンチャットをオプチャグラフに手動で登録する方法です。
                </b>
            </p>
            <ul>
                <li><a href="https://openchat.line.me/jp" rel="external" target="_blank">LINEオープンチャット公式サイト</a>を開く</li>
                <li>登録したいオープンチャットを探す</li>
                <li>ページのURLをアドレスバーからコピーする</li>
                <li>下記フォームに貼り付けて登録する</li>
            </ul>
            <p>
                公式サイトの検索機能でヒットしない場合、Google検索などで見つかる場合があります。
            </p>
        </section>
        <!-- 送信後のレスポンス -->
        <?php if (session()->has('id')) : ?>
            <!-- 登録完了 or 登録済 -->
            <hr class="ht-top-header">
            <sup class="add-openchat-message success" id="add-openchat-describedby"><?php echo h(session('message')) ?></sup>
            <div class="openchat-item add-openchat-form-item">
                <a class="link-overlay unset" href="<?php echo url('/oc/' . $requestOpenChat['id']) ?>" tabindex="-1"></a>
                <img alt class="openchat-item-img" src="<?php echo imgPreviewUrl($requestOpenChat['img_url']) ?>">
                <h2 class="unset">
                    <a class="openchat-item-title unset" href="<?php echo url('/oc/' . $requestOpenChat['id']) ?>"><?php echo $requestOpenChat['name'] ?></a>
                </h2>
                <p class="openchat-item-desc unset"><?php echo $requestOpenChat['description'] ?></p>
                <footer class="openchat-item-lower unset">
                    <span>メンバー <?php echo $requestOpenChat['member'] ?></span>
                </footer>
            </div>
        <?php elseif (session()->has('message')) : ?>
            <!-- 無効なURLの場合 -->
            <hr class="ht-top-header">
            <div style="font-size: 17px; margin: 1rem;" class="add-openchat-message false" id="add-openchat-describedby"><?php echo h(session('message')) ?></div>
        <?php endif ?>
        <?php foreach (session()->getError() as $error) : ?>
            <!-- その他エラーメッセージ -->
            <hr class="ht-top-header">
            <div style="font-size: 17px; margin: 1rem;" class="add-openchat-message false" id="add-openchat-describedby"><?php echo h($error['message']) ?></div>
        <?php endforeach ?>
        <form class="add-openchat-form unset" id="add-openchat-form" action="/oc" method="POST">
            <labe>公式サイトのURL</labe>
            <input name="url" id="add-openchat-input-url" placeholder="https://openchat.line.me/jp/cover/..." spellcheck="false" type="text" aria-describedby="add-openchat-describedby" autocomplete="off">
            <span class="add-openchat-message" id="add-openchat-describedby">正しいURLを入力してください</span>
            <p>登録に使用できるのは招待用のURLではなく、公式サイトのURLです。</p>
            <button type="submit" name="submit" class="ellipse-btn add-openchat" disabled>登録する</button>
        </form>
        <p>
            公式サイトに掲載されていないオープンチャットや、そのURLがわからない場合は登録ができません。
        </p>
    </main>
    <?php viewComponent('footer_inner') ?>
    <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
    <script type="module">
        import {
            OpenChatUrlValidator
        } from '<?php echo fileUrl('/js/OpenChatUrlValidator.js', urlRoot: '') ?>';

        const addOpenChatForm = document.getElementById('add-openchat-form')
        const inputValidator = new OpenChatUrlValidator(addOpenChatForm)
        addOpenChatForm.addEventListener('input', () => inputValidator.handle())

        // 古いSafariの対策
        addOpenChatForm.addEventListener('submit', e => e.target.elements['submit'].disabled && e.preventDefault())
    </script>
    <script>
        const admin = <?php echo isAdmin() ? 1 : 0; ?>;
    </script>
    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
</body>

</html>