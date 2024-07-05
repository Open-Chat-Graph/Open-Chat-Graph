<?php

use App\Services\Accreditation\Enum\ExamType;
use App\Views\Content\Accreditation\AccreditationAdminViewContent;

$view = new AccreditationAdminViewContent($controller);

/**
 * @var \App\Models\Accreditation\AccreditationHomePageModel $model
 */
?>

<!DOCTYPE html>
<html lang="ja">
<?php $view->head(false) ?>

<body>
    <?php $view->header() ?>
    <style>
        .graph-ifame {
            width: 100%;
            max-width: 512px;
            aspect-ratio: 2/1;
            overflow: hidden;
        }

        .graph-ifame iframe {
            border: 0;
            width: 100%;
            height: 200vh
        }

        .share-menu-item {
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
        }

        .share-menu-icon {
            width: 32px;
            height: 32px;
            margin: auto;
            background-repeat: no-repeat;
            background-size: contain;
            display: block;
        }

        .share-menu-icon.copy {
            width: 26px;
            height: 26px;
            margin: auto;
        }

        .share-menu-icon-twitter {
            background-image: url(/assets/twitter_x.svg);
            background-color: #000000;
            border-radius: 6px;
        }

        .share-menu-icon-line {
            background-image: url(/assets/line.svg);
            border-radius: 6px;
        }

        .copy-btn-icon {
            background-image: url(/assets/copy_icon_c.svg);
        }

        .list-wrapper {
            margin: 24px 0;
            display: block;
            text-decoration: none;
        }

        .question-link {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
            word-break: break-all;
            font-size: 19px;
            line-height: 1.4;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial,
                sans-serif;
            text-decoration: unset;
            font-weight: normal;
            width: fit-content;
            max-width: 583px;
            margin-top: 6px;
            color: #1558d6;
        }

        .exam-title-chip {
            margin-right: 6px;
            display: inline-block;
            padding: 4px 7px;
            border-radius: 2rem;
            font-size: 11px;
            line-height: 1;
            color: #777;
            border: 1px solid;
            font-weight: bold;
        }

        @media screen and (min-width: 512px) {
            .list-wrapper:hover .question-link {
                text-decoration: underline 2px;
            }

            .question-link {
                -webkit-line-clamp: 1;
                color: #1a0dab;
                max-width: 590px;
                font-size: 19px;
            }
        }
    </style>
    <main>
        <?php $view->mainTab(false) ?>
        <hr>
        <div>
            <h2>最近の投稿</h2>
            <?php $list = $model->getQuestionList(5); ?>
            <?php foreach ($list as $q) : ?>
                <?php $type = ExamType::from($q->type) ?>
                <a class="list-wrapper" href="./../<?php echo $q->type . "/" . ($q->publishing ? 'published' : 'unpublished') ?>#id-<?php echo $q->id ?>">
                    <div style="display: flex; align-items: center;">
                        <span class="exam-title-chip"><?php echo $view->getExamTypeName($type) ?></span>
                        <small style="margin-right: 4px; color: #111;"><?php echo $q->user_name ?></small>
                        <small style="margin-right: 4px; color: #777;"><?php echo timeElapsedString($q->created_at) ?></small>
                        <small style="margin-right: 4px; color: #aaa; font-weight: <?php echo $q->publishing ? 'normal' : 'bold' ?>;"><?php echo $q->publishing ? '出題中' : '未公開' ?></small>
                    </div>
                    <span class="question-link"><?php echo $q->question ?></span>
                </a>
            <?php endforeach ?>
        </div>
        <hr>
        <div>
            <h2>問題数</h2>
            <table>
                <thead>
                    <tr>
                        <th></th>
                        <th>問題数</th>
                        <th>出題中</th>
                        <th>未公開</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counts = $model->getQuestionCount(); ?>
                    <?php foreach (ExamType::cases() as $type) : ?>
                        <tr>
                            <td style="font-weight: bold;">
                                <?php echo $view->getExamTypeName($type) ?>
                            </td>
                            <td>
                                <span><?php echo $counts['total_count_' . $type->value] ?> 件</span>
                            </td>
                            <td>
                                <span><?php echo $counts['publishing_count_' . $type->value] ?> 件</span>
                            </td>
                            <td>
                                <span><?php echo $counts['total_count_' . $type->value] - $counts['publishing_count_' . $type->value] ?> 件</span>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    <tr>
                        <td>合計</td>
                        <td>
                            <span style="font-weight: bold;"><?php echo $counts['total_count_bronze'] + $counts['total_count_silver'] + $counts['total_count_gold'] ?> 件</span>
                        </td>
                        <td>
                            <span style="font-weight: bold;"><?php echo $counts['publishing_count_bronze'] + $counts['publishing_count_silver'] + $counts['publishing_count_gold'] ?> 件</span>
                        </td>
                        <td>
                            <span style="font-weight: bold;"><?php echo ($counts['total_count_bronze'] + $counts['total_count_silver'] + $counts['total_count_gold']) - ($counts['publishing_count_bronze'] + $counts['publishing_count_silver'] + $counts['publishing_count_gold']) ?> 件</span>
                        </td>
                    </tr>
                </tbody>
            </table>

        </div>
        <hr>
        <a href="/accreditation" target="_blank" style="font-size: 16px; font-weight: bold; color: #1558d6;">オプチャ検定｜公式サイト</a>
        <div style="display: flex; gap: 24px; margin-top: 1rem; flex-wrap: wrap; align-items: center;">
            <small style="word-break: keep-all; text-wrap: nowrap; margin-right: -10px">シェア</small>

            <div class="share-menu-item unset" onclick="copyUrl('LINEオープンチャットを快適に利用できる知識を楽しく学ぼう！\nオプチャ検定｜公式サイト\nhttps://openchat-review.me/accreditation')">
                <span class="copy-btn-icon share-menu-icon copy"></span>
            </div>
            <a class="share-menu-item unset" href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://openchat-review.me/accreditation') ?>&text=<?php echo urlencode("LINEオープンチャットを快適に利用できる知識を楽しく学ぼう！\nオプチャ検定｜公式サイト\n") ?>" rel="nofollow noopener" target="_blank" title="ポスト">
                <span class="share-menu-icon-twitter share-menu-icon"></span>
            </a>

            <a class="share-menu-item unset" href="http://line.me/R/msg/text/?<?php echo urlencode("LINEオープンチャットを快適に利用できる知識を楽しく学ぼう！\nオプチャ検定｜公式サイト\nhttps://openchat-review.me/accreditation") ?>" rel="nofollow noopener" target="_blank" title="LINEで送る">
                <span class="share-menu-icon-line share-menu-icon"></span>
            </a>
        </div>
        <br>

        <details>
            <summary style="width: fit-content; font-size: 13px;">オプチャ検定のページ訪問回数を見る</summary>
            <br>
            <div class="graph-ifame">
                <iframe loading=lazy src="https://lookerstudio.google.com/embed/reporting/12373f52-b8f2-42d1-9935-d2ffd6bebfa2/page/usU4D" frameborder="0" allowfullscreen sandbox="allow-storage-access-by-user-activation allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe>
            </div>
            <br>
            <small>データの反映にラグがあるため、最新日の数字は最大2日後ぐらいに確定します</small>
        </details>

        <?php if ($view->controller->profileArray) : ?>
            <hr>
            <small style="font-size: 15px;">
                <span style="margin-right: 4px; color:#111; font-weight: bold;">プロフィール設定</span>
                <a href="./profile">
                    <?php if ($view->controller->profileArray['is_admin']) : ?>
                        👑
                    <?php endif ?>
                    <span style="text-decoration: underline;"> <?php echo $view->controller->profileArray['name'] ?></span>
                </a>
            </small>
        <?php endif ?>
        <hr>
        <?php $view->termHome() ?>
    </main>
    <?php $view->footer() ?>
    <script>
        async function copyUrl(text) {
            try {
                await navigator.clipboard.writeText(text)
                alert("リンクをコピーしました");
            } catch {
                alert("コピーできませんでした\n(非対応ブラウザ)");
            }
        }
    </script>
</body>

</html>