<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php

use App\Config\AppConfig;
use Shared\MimimalCmsConfig;
use App\Views\Ads\GoogleAdsense as GAd;

$enableAdsense = MimimalCmsConfig::$urlRoot === ''; // 日本語版のみ広告表示

/** @var \App\Services\StaticData\Dto\StaticTopPageDto $dto */
viewComponent('head', compact('_css', '_meta', '_schema')) ?>

<body class="top-page">
    <?php if ($enableAdsense): ?>
        <?php \App\Views\Ads\GoogleAdsense::gTag() ?>
    <?php endif ?>

    <?php viewComponent('site_header', compact('_updatedAt')) ?>
    <div class="pad-side-top-ranking body" style="overflow: hidden; padding-top: 0;">

        <div style="padding: 0 1rem; margin-bottom: 1rem;">
            <div style="margin: 1rem 0;">
                <small style="display: block; color: #000; font-size: 11px; font-weight: bold; line-height: 1;">LINE</small>
                <h1 style="margin: 0; padding: 0; font-size: 28px; font-weight: bold; line-height: 1;">OPENCHAT Graph <?php echo MimimalCmsConfig::$urlRoot ? strtoupper(str_replace('/', '', MimimalCmsConfig::$urlRoot)) : '' ?>📈</h1>
            </div>
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
        </div>
        <?php if (MimimalCmsConfig::$urlRoot === ''): // TODO: 日本以外ではマイリストが無効
        ?>
            <div id="myListDiv" style="transition: all 0.3s; opacity: 0;"></div>
        <?php endif ?>
        <hr class="hr-top" style="margin-bottom: 8px;">
        <div class="modify-top-padding" style="margin-bottom: 0rem;">
            <?php viewComponent('topic_tag', ['topPageDto' => $dto]);
            AppConfig::$listLimitTopRanking = 10; ?>
        </div>
        <hr class="hr-top" style="margin-bottom: 8px;">
        <?php viewComponent('top_ranking_comment_list_hour', compact('dto')) ?>
        <hr class="hr-top" style="margin-bottom: 8px;">
        <?php viewComponent('top_ranking_comment_list_hour24', compact('dto')) ?>
        <hr class="hr-top" style="margin-bottom: 8px;">
        <?php if (MimimalCmsConfig::$urlRoot === ''): ?>
            <?php viewComponent('top_ranking_recent_comments') ?>
        <?php endif ?>

        <hr class="hr-top" style="margin-bottom: 8px;">
        <?php viewComponent('top_ranking_comment_list_week', compact('dto')) ?>

        <hr class="hr-top" style="margin-bottom: 8px;">
        <?php viewComponent('top_ranking_comment_list_member', compact('dto')) ?>

        <hr class="hr-top" style="margin-bottom: 8px;">
        <?php viewComponent('recommend_list2', ['recommend' => $officialDto, 'id' => 0, 'showTags' => true, 'disableGAd' => true]) ?>
        <hr class="hr-top" style="margin-bottom: 8px;">
        <?php viewComponent('recommend_list2', ['recommend' => $officialDto2, 'id' => 0, 'showTags' => true, 'disableGAd' => true]) ?>

        <?php viewComponent('footer_inner') ?>
        <div class="refresh-time" style="width: fit-content; margin: auto; padding-bottom: 0.5rem; margin-top: -9px;">
            <div class="refresh-icon"></div><time style="font-size: 11px; color: #b7b7b7; margin-left:3px" datetime="<?php echo $_updatedAt->format(\DateTime::ATOM) ?>"><?php echo $_updatedAt->format('Y/n/j G:i') ?></time>
        </div>
    </div>
    <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>

    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
    <?php if ($enableAdsense): ?>
        <script defer src="<?php echo fileurl("/js/security.js", urlRoot: '') ?>"></script>
    <?php endif ?>
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
        <script type="module">
            import {
                getComment
            } from '<?php echo fileUrl('/js/fetchComment.js', urlRoot: '') ?>'

            getComment(0, '<?php echo MimimalCmsConfig::$urlRoot ?>')
        </script>
    <?php endif ?>
</body>

</html>