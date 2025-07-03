<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php

use App\Config\AppConfig;
use App\Views\Ads\GoogleAdsence as GAd;
use Shared\MimimalCmsConfig;

/** @var \App\Services\StaticData\Dto\StaticTopPageDto $dto */
viewComponent('head', compact('_css', '_meta', '_schema') + ['dataOverlays' => 'bottom', 'disableGAd' => true]) ?>

<body class="top-page">
    <?php viewComponent('site_header', compact('_updatedAt')) ?>

    <div class="pad-side-top-ranking body" style="overflow: hidden; padding-top: 0;">
        <div style="padding: 1rem;">
            <small style="display: block; color: #000; font-size: 9px; font-weight: bold;">LINE</small>
            <h1 style="margin: 0; padding: 0; font-size: 50px; font-weight: bold; line-height: 1;">OPENCHAT Graph📈</h1>
            <small style="display: block; color: #000; font-size: 9px; font-weight: bold; margin-top: 1rem;">おぷちゃぐらふ</small>
        </div>
        <section class="top-ranking top-btns">
            <a style="margin: 0;" class="top-ranking-readMore unset ranking-url white-btn" href="<?php echo url('policy') ?>">
                <span class="ranking-readMore"><?php echo t('オプチャグラフについて') ?></span>
            </a>
        </section>
        <hr style="margin: 1rem 0;">
        <div class="modify-top-padding">
            <?php viewComponent('topic_tag', ['topPageDto' => $dto]) ?>
            <hr style="margin: 1rem 0;">
        </div>
        <?php if ($dto->recentCommentList): ?>
            <?php viewComponent('top_ranking_recent_comments', ['recentCommentList' => $dto->recentCommentList]) ?>
        <?php endif ?>
        <?php if (MimimalCmsConfig::$urlRoot === ''): // TODO: 日本以外ではマイリストが無効
        ?>
            <hr style="margin: 1rem 0;">
            <div id="myListDiv" style="transition: all 0.3s; opacity: 0;"></div>
            <hr style="margin: 1rem 0;">
        <?php endif ?>
        <?php viewComponent('top_ranking_comment_list_hour', compact('dto')) ?>
        <hr style="margin: 1rem 0;">
        <?php viewComponent('top_ranking_comment_list_hour24', compact('dto')) ?>
        <hr style="margin: 1rem 0;">
        <?php viewComponent('top_ranking_comment_list_week', compact('dto')) ?>
        <hr style="margin: 1rem 0;">
        <?php viewComponent('recommend_list2', ['recommend' => $officialDto, 'id' => 0, 'showTags' => true, 'disableGAd' => true]) ?>
        <hr style="margin: 1rem 0;">
        <?php viewComponent('recommend_list2', ['recommend' => $officialDto2, 'id' => 0, 'showTags' => true, 'disableGAd' => true]) ?>
        <hr style="margin: 1rem 0;">
        <?php viewComponent('top_ranking_comment_list_member', compact('dto')) ?>
        <?php viewComponent('footer_inner') ?>

        <div class="refresh-time" style="width: fit-content; margin: auto; padding-bottom: 0.5rem; margin-top: -9px;">
            <div class="refresh-icon"></div><time style="font-size: 11px; color: #b7b7b7; margin-left:3px" datetime="<?php echo $_updatedAt->format(\DateTime::ATOM) ?>"><?php echo $_updatedAt->format('Y/n/j G:i') ?></time>
        </div>
    </div>
    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>

    <?php if (MimimalCmsConfig::$urlRoot === ''): // TODO: 日本以外ではマイリストが無効
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