<!DOCTYPE html>
<html lang="ja">

<head prefix="og: http://ogp.me/ns#">
    <?php echo gTag(\App\Config\AppConfig::GTM_ID) ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <meta name="theme-color" content="#800080" />

    <link rel="icon" type="image/png" href="<?php echo fileUrl('assets/study_icon.png') ?>">

    <?php if (!isset($isCrawler) || !$isCrawler) : ?>
        <script defer="defer" src="<?php echo fileUrl($_js) ?>"></script>
        <link rel="stylesheet" href="<?php echo fileUrl($_css) ?>">
    <?php else : ?>
        <link rel="stylesheet" href="<?php echo fileUrl('style/mvp.css') ?>">
    <?php endif ?>

    <link rel="canonical" hrefs="<?php echo $canonical ?>">

    <title><?php echo $title ?></title>
    <meta name="description" content="<?php echo $description ?>">
    <meta property="og:locale" content="ja_JP">
    <meta property="og:url" content="<?php echo $canonical ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo $title ?>">
    <meta property="og:description" content="<?php echo $description ?>">
    <meta property="og:site_name" content="オプチャグラフ">
    <meta property="og:image" content="<?php echo $ogp ?>">
    <meta name="twitter:card" content="summary">
    <meta name="thumbnail" content="<?php echo fileUrl('assets/ogp-accreditation.png') ?>" />
    <?php /** @var \App\Services\Accreditation\QuizApi\Dto\Topic $_argDto */
    echo \App\Services\Accreditation\AccreditationSchemaGenerator::breadcrumbList(
        isset($edited_at) ? '問題' : '',
        isset($edited_at) ? "?id={$_argDto->questions[0]->id}" : '',
    ) ?>
</head>

<body style="margin: 0;">
    <?php if (!isset($isCrawler) || !$isCrawler) : ?>
        <script type="application/json" id="arg-dto">
            <?php echo json_encode($_argDto, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
        </script>
        <?php if (isset($_argDto_silver)) : ?>
            <script type="application/json" id="arg-dto-silver">
                <?php echo json_encode($_argDto_silver, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
            </script>
        <?php endif ?>
        <?php if (isset($_argDto_gold)) : ?>
            <script type="application/json" id="arg-dto-gold">
                <?php echo json_encode($_argDto_gold, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); 
                ?>
            </script>
        <?php endif ?>
        <noscript>You need to enable JavaScript to run this app.</noscript>
        <div id="root"></div>
        <?php if (isset($edited_at)) : ?>
            <span style="font-size: 10px; color: #b7b7b7; position: absolute; top:4px; left:4px;">最終更新 <time datetime="<?php echo (new DateTime($edited_at))->format(DateTime::ATOM) ?>"><?php echo (new DateTime($edited_at))->format('Y/n/j') ?></time></span>
        <?php endif ?>

    <?php elseif (isset($edited_at, $created_at)) :
        /**
         * クイズ単品ページ
         */ ?>
        <header>
            <h1><?php echo $_argDto->questions[0]->question ?></h1>
        </header>
        <main>
            <article>
                <h2><?php echo $_argDto->questions[0]->question ?></h2>
                <ol>
                    <?php foreach ($_argDto->questions[0]->choices as $choice) : ?>
                        <li data-answer="<?php echo $_argDto->questions[0]->correctAnswers[0] === $choice ? 'true' : 'false' ?>"><?php echo $choice ?></li>
                    <?php endforeach ?>
                </ol>
                <p>正解: <span data-correct-answer="true"><?php echo $_argDto->questions[0]->correctAnswers[0] ?></span></p>
                <p>解説: <?php echo $_argDto->questions[0]->explanation ?></p>
                <p>出典: <a href="<?php echo $_argDto->questions[0]->source->url ?>"><?php echo $_argDto->questions[0]->source->title ?></a></p>
                <p>出題者: <?php echo $_argDto->questions[0]->contributor->name ?></p>
                <?php if ($_argDto->questions[0]->contributor->url) : ?>
                    <p>出題者のオプチャ: <a href="<?php echo $_argDto->questions[0]->contributor->url ?>"><?php echo $_argDto->questions[0]->contributor->roomName ?></a></p>
                <?php endif ?>
                <p>作成日: <?php echo $created_at ?></p>
                <p>更新日: <time datetime="<?php echo (new DateTime($edited_at))->format(DateTime::ATOM) ?>"><?php echo $edited_at ?></time></p>
            </article>
        </main>
        <footer>
            <p>オプチャ検定は、ガイドラインやルール、管理方法などについての知識を深める場所です。LINEオープンチャットを利用する際に必要な情報を楽しく学ぶことができます。</p>
            <p><a href="/accreditation">トップ</a></p>
            <p><a href="/accreditation/login">問題投稿ページ</a></p>
            <p><a href="/policy/privacy">プライバシーポリシー</a></p>
            <p>&copy; LINEオープンチャット オプチャ検定</p>
        </footer>
        <?php echo \App\Services\Accreditation\AccreditationSchemaGenerator::singleQuiz(
            $title,
            $description,
            $ogp,
            $created_at,
            $edited_at,
            $_argDto->questions[0]->contributor->name,
            $_argDto->questions[0]->contributor->url,
            $_argDto->questions[0]->question,
            $_argDto->questions[0]->correctAnswers[0],
            $_argDto->questions[0]->choices
        ) ?>
    <?php endif ?>
</body>

</html>