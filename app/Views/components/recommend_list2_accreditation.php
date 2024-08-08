<article class="top-ranking not-rank" style="padding: 0 0 1rem 0;">
    <header class="openchat-list-title-area unset">
        <div class="openchat-list-date unset ranking-url">
            <h2 class="unset">
                <span style="line-height: 1.5; font-size: 15px; color: #111; font-weight: bold;">オプチャ検定</span>
            </h2>
        </div>
    </header>

    <ol class="openchat-item-list unset" style="margin: -14px 0 -8px 0;">
        <?php
        /** @var \App\Services\Accreditation\Recommend\AcrreditationRecommend $acrreditation */
        foreach ($acrreditation->getRandomQuestions(3) as $question) : ?>
            <li class="openchat-item unset" style="padding-left: 0; min-height: 0; cursor: unset; margin: 6px 1.5rem 6px 0">
                <div style="display: flex; flex-direction: row; flex-wrap:nowrap;">
                    <span style="user-select: none; min-width:20px; display:block; color: #777;" aria-hidden="true">・</span>
                    <a href="<?php echo url('accreditation?id=' . $question->id) ?>" class="openchat-item-desc" style=" color: rgb(39, 85, 172); font-size: 15px; font-weight: 500; letter-spacing: -0.3px; -webkit-line-clamp: 2; text-decoration: unset; line-height: 1.25;"><?php echo $question->question ?></a>
                </div>
            </li>
        <?php endforeach ?>
        <li class="openchat-item unset" style="padding-left: 0; min-height: 0; cursor: unset; margin: 14px 1.5rem 6px 0">
            <a style="margin-bottom: 0;" class="top-ranking-readMore unset ranking-url" href="<?php echo url('accreditation/login') ?>">
                <span class="ranking-readMore">投稿ページ</span>
            </a>
        </li>
    </ol>

</article>