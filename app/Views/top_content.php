<!DOCTYPE html>
<html lang="ja">
<?php

use App\Services\Recommend\RecommendUtility;
use App\Views\Ads\GoogleAdsence as GAd;

function greenTag($word)
{
?>
    <li>
        <a class="hour tag-btn" href="<?php echo url('recommend?tag=' . urlencode(htmlspecialchars_decode($word))) ?>">
            <?php echo RecommendUtility::extractTag($word) ?>
            <svg class="MuiSvgIcon-root MuiSvgIcon-fontSizeMedium show-north css-162gv95" focusable="false" aria-hidden="true" viewBox="0 0 24 24" data-testid="NorthIcon">
                <path d="m5 9 1.41 1.41L11 5.83V22h2V5.83l4.59 4.59L19 9l-7-7-7 7z"></path>
            </svg>
        </a>
    </li>
<?php
}

/** @var \App\Services\StaticData\Dto\StaticTopPageDto $dto */
viewComponent('head', compact('_css', '_meta', '_schema')) ?>

<body>
    <?php viewComponent('site_header', compact('_updatedAt')) ?>
    <div class="pad-side-top-ranking pad-side-top-list body" style="overflow: hidden; padding-top: 8px;">

        <section class="top-ranking top-btns top">
            <a style="margin-bottom: 0;" class="top-ranking-readMore unset ranking-url" href="<?php echo url('ranking?list=daily') ?>">
                <span class="ranking-readMore">過去24時間の人数増加を見る<span class="small">24カテゴリー</span></span>
            </a>
        </section>
        <hr class="hr-bottom">

        <?php GAd::output(GAd::AD_SLOTS['siteTopWide']) ?>

        <article class="top-ranking">
            <?php if ($tags) : ?>
                <div>
                    <header class="openchat-list-title-area unset" style="margin-bottom: 10px;">
                        <div class="openchat-list-date unset ranking-url">
                            <h2 class="unset">
                                <span class="openchat-list-title">いま人数急増中のテーマ</span>
                                <span aria-hidden="true" style="font-size: 9px; user-select: none; margin-bottom: px;margin-left: -3px;">🚀</span>
                            </h2>
                            <span style="font-weight: normal; color:#aaa; font-size:13px; margin: 0"><?php echo $dto->hourlyUpdatedAt->format('G:i') ?></span>
                        </div>
                    </header>

                    <ul class="tag-list">
                        <?php $hourCount = count($tags['hour']); ?>

                        <?php foreach ($tags['hour'] as $key => $word) : ?>
                            <?php greenTag($word) ?>
                        <?php endforeach ?>

                        <?php foreach ($tags['hour24'] as $key => $word) : ?>
                            <li>
                                <a class="tag-btn" href="<?php echo url('recommend?tag=' . urlencode(htmlspecialchars_decode($word))) ?>">
                                    <?php echo RecommendUtility::extractTag($word) ?>
                                </a>
                            </li>
                        <?php endforeach ?>

                        <?php if (count($tags['hour']) + count($tags['hour24']) > 41) : ?>
                            <li id="open-btn-li">
                                <button class="unset tag-btn open-btn" onclick="this.parentElement.parentElement.classList.toggle('open')"></button>
                            </li>
                        <?php endif ?>
                    </ul>
                </div>
            <?php endif ?>
        </article>
        <hr class="hr-bottom">

        <?php GAd::output(GAd::AD_SLOTS['siteTopRectangle']) ?>

        <div id="myListDiv" style="transition: all 0.3s; opacity: 0;"></div>
        <?php if ($newComment) : ?>
            <?php viewComponent('top_ranking_recent_comments', compact('dto')) ?>
            <hr class="hr-bottom">

            <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

            <?php viewComponent('top_ranking_comment_list_hour', compact('dto')) ?>
            <hr class="hr-bottom">

            <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

            <?php viewComponent('top_ranking_comment_list_hour24', compact('dto')) ?>
            <?php //viewComponent('recommend_list2_accreditation', compact('acrreditation')) 
            ?>
            <hr class="hr-bottom">

            <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

            <?php viewComponent('recommend_list2', ['recommend' => $officialDto, 'id' => 0, 'showTags' => true]) ?>
            <hr class="hr-bottom">

            <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

            <?php viewComponent('recommend_list2', ['recommend' => $officialDto2, 'id' => 0, 'showTags' => true]) ?>
            <hr class="hr-bottom">

            <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

            <?php viewComponent('top_ranking_comment_list_member', compact('dto')) ?>
            <hr class="hr-bottom">

            <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

            <?php viewComponent('top_ranking_comment_list_week', compact('dto')) ?>
        <?php else : ?>
            <?php viewComponent('top_ranking_comment_list_hour', compact('dto')) ?>
            <hr class="hr-bottom">

            <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

            <?php viewComponent('top_ranking_comment_list_hour24', compact('dto')) ?>
            <?php //viewComponent('recommend_list2_accreditation', compact('acrreditation')) 
            ?>
            <hr class="hr-bottom">

            <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

            <?php viewComponent('recommend_list2', ['recommend' => $officialDto, 'id' => 0, 'showTags' => true]) ?>
            <hr class="hr-bottom">

            <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

            <?php viewComponent('recommend_list2', ['recommend' => $officialDto2, 'id' => 0, 'showTags' => true]) ?>
            <hr class="hr-bottom">

            <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

            <?php viewComponent('top_ranking_recent_comments', compact('dto')) ?>
            <hr class="hr-bottom">

            <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

            <?php viewComponent('top_ranking_comment_list_member', compact('dto')) ?>
            <hr class="hr-bottom">

            <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

            <?php viewComponent('top_ranking_comment_list_week', compact('dto')) ?>
        <?php endif ?>
        <hr class="hr-bottom">

        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

        <section class="top-ranking top-btns" style="padding-top: 1rem;">
            <a style="margin-bottom: 0;" class="top-ranking-readMore unset ranking-url" href="<?php echo url('official-ranking') ?>">
                <span class="ranking-readMore">前回(1時間前)の公式急上昇と人数推移</span>
            </a>
            <a class="top-ranking-readMore unset" style="margin:0" href="<?php echo url('labs') ?>">
                <span class="ranking-readMore" style="display: flex; align-items: center;">
                    <svg style="color: #111; fill: currentColor; display: inline-block; margin-right: 4px" focusable="false" height="18px" viewBox="0 -960 960 960" width="18px">
                        <path d="M209-120q-42 0-70.5-28.5T110-217q0-14 3-25.5t9-21.5l228-341q10-14 15-31t5-34v-110h-20q-13 0-21.5-8.5T320-810q0-13 8.5-21.5T350-840h260q13 0 21.5 8.5T640-810q0 13-8.5 21.5T610-780h-20v110q0 17 5 34t15 31l227 341q6 9 9.5 20.5T850-217q0 41-28 69t-69 28H209Zm221-660v110q0 26-7.5 50.5T401-573L276-385q-6 8-8.5 16t-2.5 16q0 23 17 39.5t42 16.5q28 0 56-12t80-47q69-45 103.5-62.5T633-443q4-1 5.5-4.5t-.5-7.5l-78-117q-15-21-22.5-46t-7.5-52v-110H430Z"></path>
                    </svg>
                    <span style="display: inline-block; line-height: 1;">分析Labs</span>
                </span>
            </a>
        </section>
        <hr class="hr-bottom">

        <footer class="footer-elem-outer">
            <?php viewComponent('footer_share_nav', ['title' => $_meta->title]) ?>
            <?php viewComponent('footer_inner') ?>
        </footer>
        <div class="refresh-time" style="width: fit-content; margin: auto; padding-bottom: 0.5rem;">
            <div class="refresh-icon"></div><time style="font-size: 11px; color: #777; margin-left:3px" datetime="<?php echo $_updatedAt->format(\DateTime::ATOM) ?>"><?php echo $_updatedAt->format('Y/n/j G:i') ?></time>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const num = document.querySelectorAll('ins').length;
            for (let i = 0; i < num; i++) {
                (adsbygoogle = window.adsbygoogle || []).push({});
            }
        });
    </script>
    <script defer src="<?php echo fileurl("/js/site_header_footer.js") ?>"></script>
    <script>
        let lastList = ''

        function fetchMyList(name) {
            const cookieRegex = new RegExp(`(^|;)\\s*${name}\\s*=\\s*([^;]+)`)
            const cookieMatch = document.cookie.match(cookieRegex)
            const myListDiv = document.getElementById('myListDiv')
            if (!cookieMatch) {
                myListDiv.textContent && (myListDiv.textContent = '')
                return
            }

            fetch('mylist-api')
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

        let lastComment = ''

        function getCookieValue(key) {
            const cookies = document.cookie.split(';')
            const foundCookie = cookies.find(
                (cookie) => cookie.split('=')[0].trim() === key.trim()
            )
            if (foundCookie) {
                const cookieValue = decodeURIComponent(foundCookie.split('=')[1])
                return cookieValue
            }
            return ''
        }

        function fetchComment(name) {
            const cookieValue = getCookieValue(name)
            if (!cookieValue) {
                return
            }

            const comment = document.getElementById('recent_comment')

            fetch('recent-comment-api')
                .then((res) => {
                    if (res.status === 200)
                        return res.text();
                    else
                        throw new Error()
                })
                .then((data) => {
                    if (lastList === data)
                        return

                    comment.textContent = ''
                    comment.insertAdjacentHTML('afterbegin', data)
                })
                .catch(error => console.error('エラー', error))
        }


        window.addEventListener("pageshow", function(event) {
            fetchMyList('myList')
            fetchComment('comment_flag')
        });
    </script>
    <?php echo $_meta->generateTopPageSchema() ?>
</body>

</html>