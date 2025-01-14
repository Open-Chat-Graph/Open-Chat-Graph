<!DOCTYPE html>
<html lang="<?php echo t('ja_JP') ?>">
<?php

use App\Config\AppConfig;
use App\Views\Ads\GoogleAdsence as GAd;
use Shared\MimimalCmsConfig;

/** @var \App\Services\StaticData\Dto\StaticTopPageDto $dto */
viewComponent('head', compact('_css', '_meta', '_schema')) ?>

<body>
    <?php viewComponent('site_header', compact('_updatedAt')) ?>
    <div class="pad-side-top-ranking body" style="overflow: hidden; padding-top: 0px;">
        <div class="modify-top-padding">
            <?php viewComponent('topic_tag', ['topPageDto' => $dto]) ?>
        </div>
        <?php GAd::output(GAd::AD_SLOTS['siteTopWide']) ?>
        <?php if ($dto->recentCommentList): ?>
            <div id="myListDiv" style="transition: all 0.3s; opacity: 0;"></div>
            <?php viewComponent('top_ranking_recent_comments', ['recentCommentList' => $dto->recentCommentList]) ?>
            <?php GAd::output(GAd::AD_SLOTS['siteSeparatorRectangle']) ?>
        <?php endif ?>

        <?php viewComponent('top_ranking_comment_list_hour', compact('dto')) ?>

        <?php viewComponent('top_ranking_comment_list_hour24', compact('dto')) ?>

        <?php viewComponent('recommend_list2', ['recommend' => $officialDto, 'id' => 0, 'showTags' => true, 'disableGAd' => true]) ?>

        <?php viewComponent('recommend_list2', ['recommend' => $officialDto2, 'id' => 0, 'showTags' => true, 'disableGAd' => true]) ?>


        <?php viewComponent('top_ranking_comment_list_member', compact('dto')) ?>

        <?php viewComponent('top_ranking_comment_list_week', compact('dto')) ?>

        <footer class="footer-elem-outer">
            <?php viewComponent('footer_share_nav', ['title' => $_meta->title]) ?>
            <?php viewComponent('footer_inner') ?>
        </footer>
        <div class="refresh-time" style="width: fit-content; margin: auto; padding-bottom: 0.5rem;">
            <div class="refresh-icon"></div><time style="font-size: 11px; color: #777; margin-left:3px" datetime="<?php echo $_updatedAt->format(\DateTime::ATOM) ?>"><?php echo $_updatedAt->format('Y/n/j G:i') ?></time>
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

    <?php echo $_meta->generateTopPageSchema() ?>
</body>

</html>