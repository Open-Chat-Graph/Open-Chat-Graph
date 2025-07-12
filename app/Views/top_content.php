<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php

use App\Config\AppConfig;
use Shared\MimimalCmsConfig;
use App\Views\Ads\GoogleAdsence as GAd;

/** @var \App\Services\StaticData\Dto\StaticTopPageDto $dto */
viewComponent('head', compact('_css', '_meta', '_schema')) ?>

<body class="top-page">
    <?php viewComponent('site_header', compact('_updatedAt')) ?>
    <div class="pad-side-top-ranking body" style="overflow: hidden; padding-top: 0;">
        <div class="ads-wropper">
            <?php GAd::output(GAd::AD_SLOTS['siteTopRectangle']) ?>
        </div>
        <div style="padding: 1rem; padding-top: 0; padding-bottom: .5rem;">
            <small style="display: block; color: #000; font-size: 10px; margin: .5rem 0 1rem 0;">
                <?php
                $languages = array_keys(AppConfig::LINE_OPEN_URL);

                ?>

                <?php foreach ($languages as $key => $lang): ?>
                    <?php if ($lang === MimimalCmsConfig::$urlRoot): ?>
                        <span style="color: inherit; font-weight: bold;"><?php echo t('オプチャグラフ', $lang) ?></span>
                    <?php else: ?>
                        <a href="<?php echo url(["urlRoot" => "", "paths" => [$lang]]) ?>" style="color: inherit;"><?php echo t('オプチャグラフ', $lang) ?></a>
                    <?php endif; ?>
                    <?php if ($key !== count($languages) - 1): ?>／<?php endif; ?>
                <?php endforeach; ?>
            </small>
            <small style="display: block; color: #000; font-size: 11px; font-weight: bold; line-height: 1;">LINE</small>
            <h1 style="margin: 0; padding: 0; font-size: 28px; font-weight: bold; line-height: 1;">OPENCHAT Graph <?php echo MimimalCmsConfig::$urlRoot ? strtoupper(str_replace('/', '', MimimalCmsConfig::$urlRoot)) : '' ?>📈</h1>
        </div>
        <a href="<?php echo url(["urlRoot" => "", "paths" => [str_replace('/', '', MimimalCmsConfig::$urlRoot) . '/policy']]) ?>" style="color: inherit; font-weight: bold; font-size: 13px; display: block; margin: 0.5rem 1rem; width: fit-content;"><?php echo t('オプチャグラフとは？') ?></a>
        <?php if (MimimalCmsConfig::$urlRoot === ''): // TODO: 日本以外ではマイリストが無効
        ?>
            <div id="myListDiv" style="transition: all 0.3s; opacity: 0;"></div>
        <?php endif ?>
        <?php viewComponent('top_ranking_comment_list_hour', compact('dto')) ?>
        <?php GAd::output(GAd::AD_SLOTS['siteTopWide']) ?>
        <div class="modify-top-padding">
            <?php viewComponent('topic_tag', ['topPageDto' => $dto]) ?>
        </div>
        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>
        <?php viewComponent('top_ranking_comment_list_hour24', compact('dto')) ?>
        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorWide']) ?>
        <?php viewComponent('top_ranking_comment_list_week', compact('dto')) ?>
        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorWide']) ?>
        <?php viewComponent('top_ranking_comment_list_member', compact('dto')) ?>

        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>
        <?php if ($dto->recentCommentList): ?>
            <?php viewComponent('top_ranking_recent_comments', ['recentCommentList' => $dto->recentCommentList]) ?>
        <?php endif ?>

        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>
        <?php viewComponent('recommend_list2', ['recommend' => $officialDto, 'id' => 0, 'showTags' => true, 'disableGAd' => true]) ?>
        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>
        <?php viewComponent('recommend_list2', ['recommend' => $officialDto2, 'id' => 0, 'showTags' => true, 'disableGAd' => true]) ?>

        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>
        <?php viewComponent('footer_inner') ?>
        <div class="refresh-time" style="width: fit-content; margin: auto; padding-bottom: 0.5rem; margin-top: -9px;">
            <div class="refresh-icon"></div><time style="font-size: 11px; color: #b7b7b7; margin-left:3px" datetime="<?php echo $_updatedAt->format(\DateTime::ATOM) ?>"><?php echo $_updatedAt->format('Y/n/j G:i') ?></time>
        </div>
    </div>
    <?php \App\Views\Ads\GoogleAdsence::loadAdsTag() ?>
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