<?php
use App\Config\AppConfig;
use Shared\MimimalCmsConfig;

$enableAdsense = true;
?>
<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">

<head prefix="og: http://ogp.me/ns#">
    <?php echo gTag(\App\Config\AppConfig::$gtmId) ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1, viewport-fit=cover" />
    <?php /* OSステータスバー/ツールバーの色。初期HTMLに必須（JS生成だけだとiOSが拾わないことがある）。
             実際の値は theme.js が解決テーマに合わせて即時更新する */ ?>
    <meta name="theme-color" content="#ffffff">
    <?php /* 「ホーム画面に追加」(PWA)時のみ有効: ページがステータスバー/ダイナミックアイランドの
             下まで広がり、blur付きヘッダーとコンテンツが周囲に透ける（通常のSafari縦持ちでは
             この帯はSafari描画のため不可）。black-translucent はステータス文字が常に白の
             ためライトのPWA起動では視認性が落ちるトレードオフあり */ ?>
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <?php echo $_meta ?>
    <?php if ($noindex) : ?>
        <meta name="robots" content="noindex, follow, max-image-preview:large">
    <?php else : ?>
        <meta name="robots" content="max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <?php endif ?>
    <link rel="icon" type="image/png" href="<?php echo fileUrl(\App\Config\AppConfig::SITE_ICON_FILE_PATH, urlRoot: '') ?>">
    <?php /* テーマ確定はCSSより先（FOUC防止のため同期読み込み） */ ?>
    <script src="<?php echo fileUrl('/js/theme.js', urlRoot: '') ?>"></script>
    <link rel="stylesheet" href="<?php echo fileUrl('style/tokens.css', urlRoot: '') ?>">
    <?php foreach ($_css as $css) : ?>
        <link rel="stylesheet" href="<?php echo fileUrl($css, urlRoot: '') ?>">
    <?php endforeach ?>
    <?php if ($legacyReact && $_js) : ?>
        <script type="module" crossorigin src="<?php echo fileUrl($_js, urlRoot: '') ?>"></script>
    <?php endif ?>
    <link rel="canonical" href="<?php echo h($canonical) ?>">
    <?php foreach ($hreflang as $language => $href) : ?>
        <link rel="alternate" hreflang="<?php echo h($language) ?>" href="<?php echo h($href) ?>">
    <?php endforeach ?>
    <link rel="alternate" type="application/json" href="<?php echo h(url('api/v1/rankings')) ?>">
    <script defer src="<?php echo fileUrl('/js/seo-observability.js', urlRoot: '') ?>"></script>
    <?php echo $_schema ?>
    <?php if ($enableAdsense): ?>
        <?php \App\Views\Ads\GoogleAdsense::gTag() ?>
    <?php endif ?>

</head>

<body class="ranking-ssr-body" style="margin: 0;">
    <?php if ($legacyReact) : ?>
        <script type="application/json" id="arg-dto">
            <?php echo json_encode($_argDto, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
        </script>
        <div id="root"></div>
    <?php else : ?>
        <?php viewComponent('site_header', ['demoteTitle' => true]) ?>
        <main class="ranking-ssr">
            <header class="ranking-ssr__hero">
                <p class="ranking-ssr__eyebrow"><?php echo t('毎時更新の公開データ') ?></p>
                <h1><?php echo $heading ?></h1>
                <p><?php echo $description ?></p>
                <p class="ranking-ssr__updated"><?php echo t('更新') ?>: <time datetime="<?php echo $_updatedAt->format(DateTimeInterface::RFC3339) ?>"><?php echo $_updatedAt->format(t('Y年n月j日 G:i')) ?></time> ・ <a href="<?php echo url('policy') ?>#methodology"><?php echo t('算出方法') ?></a></p>
            </header>

            <form class="ranking-ssr__search" method="get" action="<?php echo url('ranking') ?>" role="search">
                <label for="ranking-keyword"><?php echo t('オープンチャットを検索') ?></label>
                <div><input id="ranking-keyword" name="keyword" type="search" maxlength="100" value="<?php echo h((string)($_GET['keyword'] ?? '')) ?>"><button type="submit"><?php echo t('検索') ?></button></div>
            </form>

            <nav class="ranking-ssr__filters" aria-label="<?php echo t('ランキング期間') ?>">
                <?php foreach ([
                    'all' => t('参加人数'),
                    'hourly' => t('1時間の増加'),
                    'daily' => t('24時間の増加'),
                    'weekly' => t('1週間の増加'),
                ] as $listKey => $label) : ?>
                    <a href="<?php echo $canonical . ($listKey === 'all' ? '' : '?list=' . $listKey) ?>"<?php if ((string)($_GET['list'] ?? 'all') === $listKey) echo ' aria-current="page"' ?>><?php echo $label ?></a>
                <?php endforeach ?>
            </nav>

            <section aria-labelledby="ranking-categories-title">
                <h2 id="ranking-categories-title"><?php echo t('カテゴリーから探す') ?></h2>
                <nav class="ranking-ssr__categories">
                    <a href="<?php echo url('ranking') ?>"><?php echo t('すべて') ?></a>
                    <?php foreach (AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] as $categoryName => $categoryId) : ?>
                        <?php if ($categoryId) : ?><a href="<?php echo url('ranking/' . $categoryId) ?>"><?php echo $categoryName ?></a><?php endif ?>
                    <?php endforeach ?>
                </nav>
            </section>

            <section aria-labelledby="ranking-results-title">
                <h2 id="ranking-results-title"><?php echo $heading ?> <small>(<?php echo number_format($_total) ?>件)</small></h2>
                <ol id="ranking-room-list" class="ranking-ssr__list" start="<?php echo $_page * 20 + 1 ?>">
                    <?php foreach ($_ssrRooms as $item) : ?>
                        <?php /** @var \App\Services\PublicApi\Dto\RoomResource $room */ $room = $item['room']; ?>
                        <?php $state = $_period === 'hour' || $_period === 'day' ? ['limit' => 'hour'] : ($_period === 'week' ? ['limit' => 'week'] : []); ?>
                        <li>
                            <a class="ranking-ssr__room" href="<?php echo \App\Services\Seo\OpenChatUrlNormalizer::roomUrl($room->id, $state) ?>">
                                <strong><?php echo $room->name ?></strong>
                                <span><?php echo sprintfT('メンバー %s人', number_format($room->memberCount)) ?><?php if ($item['change'] !== null) : ?> ・ <?php echo sprintfT('%s人', signedNumF($item['change'])) ?><?php endif ?></span>
                                <span><?php echo $room->description ?></span>
                            </a>
                        </li>
                    <?php endforeach ?>
                </ol>
                <?php if (!$_ssrRooms) : ?><p><?php echo t('条件に一致するオープンチャットはありません。') ?></p><?php endif ?>
                <?php if ($_nextUrl) : ?><a id="ranking-load-more" class="ranking-ssr__more" href="<?php echo $_nextUrl ?>"><?php echo t('次の20件を表示') ?></a><?php endif ?>
            </section>
        </main>
        <?php viewComponent('footer_inner') ?>
        <script defer src="<?php echo fileUrl('/js/site_header_footer.js', urlRoot: '') ?>"></script>
        <script defer src="<?php echo fileUrl('/js/ranking-server.js', urlRoot: '') ?>"></script>
    <?php endif ?>
    <?php echo $_breadcrumbsShema ?>
    <?php if ($enableAdsense): ?>
        <?php viewComponent('ad_guard') ?>
    <?php endif ?>
</body>

</html>
