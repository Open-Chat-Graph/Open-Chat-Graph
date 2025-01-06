<!DOCTYPE html>
<html lang="ja">
<?php

use App\Views\Ads\GoogleAdsence as GAd;
use Shared\MimimalCmsConfig;

/** @var \App\Services\StaticData\Dto\StaticTopPageDto $dto */
viewComponent('head', compact('_css', '_meta', '_schema')) ?>

<body>
    <?php viewComponent('site_header', compact('_updatedAt')) ?>
    <div class="pad-side-top-ranking body" style="overflow: hidden; padding-top: 0px;">
        <?php GAd::output(GAd::AD_SLOTS['siteTopRectangle']) ?>
        <hr class="hr-top">
        <?php viewComponent('topic_tag', ['topPageDto' => $dto]) ?>

        <?php if ($dto->recentCommentList): ?>
            <div id="myListDiv" style="transition: all 0.3s; opacity: 0;"></div>
            <?php viewComponent('top_ranking_recent_comments', ['recentCommentList' => $dto->recentCommentList]) ?>
            <hr class="hr-bottom">
            <?php GAd::output(GAd::AD_SLOTS['ocSeparatorRectangle']) ?>
            <hr class="hr-top">
        <?php endif ?>

        <?php viewComponent('top_ranking_comment_list_hour', compact('dto')) ?>
        <hr class="hr-bottom">

        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorRectangle']) ?>

        <hr class="hr-top">
        <?php viewComponent('top_ranking_comment_list_hour24', compact('dto')) ?>
        <hr class="hr-bottom">

        <?php viewComponent('recommend_list2', ['recommend' => $officialDto, 'id' => 0, 'showTags' => true]) ?>

        <?php viewComponent('recommend_list2', ['recommend' => $officialDto2, 'id' => 0, 'showTags' => true]) ?>

        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorRectangle']) ?>

        <hr class="hr-top">
        <?php viewComponent('top_ranking_comment_list_member', compact('dto')) ?>
        <hr class="hr-bottom">

        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorRectangle']) ?>

        <hr class="hr-top">
        <?php viewComponent('top_ranking_comment_list_week', compact('dto')) ?>
        <hr class="hr-bottom">

        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorRectangle']) ?>

        <hr class="hr-top">
        <!-- <section class="top-ranking top-btns" style="padding-top: 1rem;">
            <a style="margin-bottom: 0;" class="top-ranking-readMore unset ranking-url white-btn" href="<?php echo url('official-ranking') ?>">
                <span class="ranking-readMore">前回(1時間前)の公式急上昇と人数推移</span>
            </a>
            <a class="top-ranking-readMore unset white-btn" style="margin:0" href="<?php echo url('labs') ?>">
                <span class="ranking-readMore" style="display: flex; align-items: center;">
                    <svg style="color: #111; fill: currentColor; display: inline-block; margin-right: 4px" focusable="false" height="18px" viewBox="0 -960 960 960" width="18px">
                        <path d="M209-120q-42 0-70.5-28.5T110-217q0-14 3-25.5t9-21.5l228-341q10-14 15-31t5-34v-110h-20q-13 0-21.5-8.5T320-810q0-13 8.5-21.5T350-840h260q13 0 21.5 8.5T640-810q0 13-8.5 21.5T610-780h-20v110q0 17 5 34t15 31l227 341q6 9 9.5 20.5T850-217q0 41-28 69t-69 28H209Zm221-660v110q0 26-7.5 50.5T401-573L276-385q-6 8-8.5 16t-2.5 16q0 23 17 39.5t42 16.5q28 0 56-12t80-47q69-45 103.5-62.5T633-443q4-1 5.5-4.5t-.5-7.5l-78-117q-15-21-22.5-46t-7.5-52v-110H430Z"></path>
                    </svg>
                    <span style="display: inline-block; line-height: 1;">分析Labs</span>
                </span>
            </a>
        </section>
        <hr class="hr-bottom"> -->

        <footer class="footer-elem-outer">
            <?php viewComponent('footer_share_nav', ['title' => $_meta->title]) ?>
            <?php viewComponent('footer_inner') ?>
        </footer>
        <div class="refresh-time" style="width: fit-content; margin: auto; padding-bottom: 0.5rem;">
            <div class="refresh-icon"></div><time style="font-size: 11px; color: #777; margin-left:3px" datetime="<?php echo $_updatedAt->format(\DateTime::ATOM) ?>"><?php echo $_updatedAt->format('Y/n/j G:i') ?></time>
        </div>
    </div>
    <?php \App\Views\Ads\GoogleAdsence::loadAdsTag() ?>
    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
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

        // TODO: 日本以外ではマイリストが無効
        if (urlRoot === '') {
            window.addEventListener("pageshow", function(event) {
                fetchMyList('myList')
            });
        }
    </script>

    <?php if (MimimalCmsConfig::$urlRoot === ''): // TODO:日本以外ではコメントが無効 
    ?>
        <script type="module">
            import {
                getComment
            } from '<?php echo fileUrl('/js/fetchComment.js', urlRoot: '') ?>'

            getComment(0, '<?php echo MimimalCmsConfig::$urlRoot ?>')
        </script>
    <?php endif ?>
    <?php echo $_meta->generateTopPageSchema() ?>
</body>

</html>