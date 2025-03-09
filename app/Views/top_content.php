<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php

use App\Config\AppConfig;
use App\Views\Ads\GoogleAdsence as GAd;
use Shared\MimimalCmsConfig;

/** @var \App\Services\StaticData\Dto\StaticTopPageDto $dto */
viewComponent('head', compact('_css', '_meta', '_schema') + ['dataOverlays' => 'bottom']) ?>

<body class="top-page">
    <?php viewComponent('site_header', compact('_updatedAt')) ?>

    <div class="pad-side-top-ranking body" style="overflow: hidden; padding-top: 0;">
        <article class="opchatgraph-intro">
            <?php if (\Shared\MimimalCmsConfig::$urlRoot === ''): ?>
                <h2>LINEオープンチャットの「今」が一目でわかる！</h2>

                <p>オプチャグラフは、LINEオープンチャットの最新人気ランキングや成長データを見やすく表示するサイトです。</p>

                <h3>こんなことがわかります！</h3>
                <ul>
                    <li><strong>人気のチャットルームが一目で確認</strong>：どのグループが今熱いのかすぐわかる</li>
                    <li><strong>わかりやすいグラフ</strong>：難しい数字も見やすいカタチで表示</li>
                    <li><strong>だれでも簡単</strong>：初心者さんからベテランまで使いこなせる</li>
                </ul>

                <h3>LINEオープンチャットって？</h3>
                <p>趣味や好きなことを共有する人たちが集まる場所です。名前を隠して参加できるので、気軽に新しい仲間と話せます。</p>

                <h3>こんな使い方ができる！</h3>
                <ul>
                    <li>今人気急上昇中のグループを見つける</li>
                    <li>自分のグループの成長ぶりをチェック</li>
                    <li>好きなジャンルの人気ランキングを調べる</li>
                </ul>
                <p>LINEオープンチャットの「今」を知るなら、オプチャグラフ！</p>
            <?php elseif (\Shared\MimimalCmsConfig::$urlRoot === '/tw'): ?>
                <h2>LINE社群的「現在」一目了然！</h2>

                <p>LINE社群流量統計是一個以易懂方式顯示LINE社群最新熱門排行和成長數據的網站。</p>

                <h3>你能了解這些內容！</h3>
                <ul>
                    <li><strong>熱門聊天室一目了然</strong>：立即知道哪些群組正熱門</li>
                    <li><strong>圖表簡單易懂</strong>：複雜的數字以清晰的形式呈現</li>
                    <li><strong>人人都能上手</strong>：從新手到老手都能輕鬆使用</li>
                </ul>

                <h3>什麼是LINE社群？</h3>
                <p>這是一個讓有共同興趣愛好的人聚集在一起的地方。你可以匿名參與，輕鬆與新朋友交流。</p>

                <h3>你可以這樣使用！</h3>
                <ul>
                    <li>發現正在快速竄紅的群組</li>
                    <li>查看自己群組的成長情況</li>
                    <li>搜尋你喜歡類別的熱門排行</li>
                </ul>

                <p>想了解LINE社群的「現在」，就上LINE社群流量統計！</p>
            <?php elseif (\Shared\MimimalCmsConfig::$urlRoot === '/th'): ?>
                <h2>ดูความเคลื่อนไหวของ LINE OpenChat ได้ในทันที!</h2>

                <p>LINE OpenChat สถิติการเข้าชมคือเว็บไซต์ที่แสดงอันดับความนิยมล่าสุดและข้อมูลการเติบโตของ LINE OpenChat ในรูปแบบที่เข้าใจง่าย</p>

                <h3>คุณจะได้รู้อะไรบ้าง!</h3>
                <ul>
                    <li><strong>ดูกลุ่มแชทยอดนิยมได้ในทันที</strong>: รู้ทันทีว่ากลุ่มไหนกำลังได้รับความนิยม</li>
                    <li><strong>กราฟที่เข้าใจง่าย</strong>: แสดงตัวเลขที่ซับซ้อนในรูปแบบที่มองเห็นได้ชัดเจน</li>
                    <li><strong>ใช้งานง่ายสำหรับทุกคน</strong>: ไม่ว่าจะเป็นมือใหม่หรือผู้เชี่ยวชาญก็ใช้งานได้</li>
                </ul>

                <h3>LINE OpenChat คืออะไร?</h3>
                <p>เป็นพื้นที่สำหรับคนที่มีความสนใจเหมือนกันมารวมตัวกัน คุณสามารถเข้าร่วมได้โดยไม่ต้องเปิดเผยตัวตน ทำให้พูดคุยกับเพื่อนใหม่ได้อย่างสบายใจ</p>

                <h3>ใช้งานได้แบบนี้!</h3>
                <ul>
                    <li>ค้นหากลุ่มที่กำลังเป็นที่นิยมอย่างรวดเร็ว</li>
                    <li>ตรวจสอบการเติบโตของกลุ่มของคุณเอง</li>
                    <li>ดูอันดับความนิยมในหมวดหมู่ที่คุณชื่นชอบ</li>
                </ul>

                <p>อยากรู้ความเคลื่อนไหวล่าสุดของ LINE OpenChat ต้อง LINE OpenChat สถิติการเข้าชม!</p>
            <?php endif ?>
            <a href="<?php echo url('policy') ?>"><?php echo t('オプチャグラフについて') ?></a>
        </article>
        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorWide']) ?>
        <div class="modify-top-padding">
            <?php viewComponent('topic_tag', ['topPageDto' => $dto]) ?>
        </div>
        <?php if ($dto->recentCommentList): ?>
            <?php viewComponent('top_ranking_recent_comments', ['recentCommentList' => $dto->recentCommentList]) ?>
        <?php endif ?>
        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>
        <div id="myListDiv" style="transition: all 0.3s; opacity: 0;"></div>

        <?php viewComponent('top_ranking_comment_list_hour', compact('dto')) ?>
        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>
        <?php viewComponent('top_ranking_comment_list_hour24', compact('dto')) ?>
        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>
        <?php viewComponent('top_ranking_comment_list_week', compact('dto')) ?>
        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>
        <?php viewComponent('recommend_list2', ['recommend' => $officialDto, 'id' => 0, 'showTags' => true, 'disableGAd' => true]) ?>
        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>
        <?php viewComponent('recommend_list2', ['recommend' => $officialDto2, 'id' => 0, 'showTags' => true, 'disableGAd' => true]) ?>
        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>
        <?php viewComponent('top_ranking_comment_list_member', compact('dto')) ?>
        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>
        <?php viewComponent('footer_inner') ?>

        <div class="refresh-time" style="width: fit-content; margin: auto; padding-bottom: 0.5rem; margin-top: -9px;">
            <div class="refresh-icon"></div><time style="font-size: 11px; color: #b7b7b7; margin-left:3px" datetime="<?php echo $_updatedAt->format(\DateTime::ATOM) ?>"><?php echo $_updatedAt->format('Y/n/j G:i') ?></time>
        </div>
    </div>
    <?php GAd::loadAdsTag() ?>
    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>

    <?php if (MimimalCmsConfig::$urlRoot === ''): // TODO:日本以外ではコメントが無効 // TODO: 日本以外ではマイリストが無効
    ?>
        <script>
            const urlRoot = '<?php echo MimimalCmsConfig::$urlRoot ?>'
            let lastList = ''

            function fetchMyList(name) {
                const cookieRegex = new RegExp(`(^|;)\\s*${name}\\s*=\\s*([^;]+)`)
                const cookieMatch = document.cookie.match(cookieRegex)
                const myListDiv = document.getElementById('myListDiv')
                if (!cookieMatch) {
                    myListDiv.textContent && (myListDiv.textContent = '')
                    return
                }

                fetch('<?php echo MimimalCmsConfig::$urlRoot ?>/mylist-api')
                    .then((res) => {
                        if (res.status === 200)
                            return res.text();
                        else
                            throw new Error()
                    })
                    .then((data) => {
                        if (lastList === data)
                            return

                        lastList = data
                        myListDiv.textContent = ''
                        myListDiv.insertAdjacentHTML('afterbegin', data)
                        myListDiv.style.opacity = '1'
                    })
                    .catch(error => console.error('エラー', error))
            }

            window.addEventListener("pageshow", function(event) {
                fetchMyList('myList')
            });
        </script>
        <?php if (AppConfig::$enableCloudflare): ?>
            <script type="module">
                import {
                    getComment
                } from '<?php echo fileUrl('/js/fetchComment.js', urlRoot: '') ?>'

                getComment(0, '<?php echo MimimalCmsConfig::$urlRoot ?>')
            </script>
        <?php else: ?>
            <script type="module">
                import {
                    applyTimeElapsedString
                } from '<?php echo fileUrl('/js/fetchComment.js') ?>'

                applyTimeElapsedString()
            </script>
        <?php endif ?>
    <?php endif ?>
</body>

</html>