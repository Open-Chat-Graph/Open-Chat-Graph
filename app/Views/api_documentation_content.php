<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php viewComponent('head', compact('_css', 'canonical', 'noindex') + ['_meta' => $_meta->generateTags()]) ?>
<body>
<?php viewComponent('site_header') ?>
<main>
  <article class="terms">
    <h1>オプチャグラフ 公開データAPI</h1>
    <p>部屋、ランキング、テーマ、サイト全体の統計を、機械処理しやすいJSONで提供します。数値はnumber、欠測はnull、日時はRFC 3339です。</p>
    <h2>エンドポイント</h2>
    <ul>
      <li><a href="<?php echo url('api/v1/rooms') ?>">部屋一覧</a> / <code>/api/v1/rooms/{id}</code></li>
      <li><a href="<?php echo url('api/v1/rankings') ?>">ランキング</a></li>
      <li><a href="<?php echo url('api/v1/themes') ?>">テーマ一覧</a> / <code>/api/v1/themes/{tag}</code></li>
      <li><a href="<?php echo url('api/v1/stats') ?>">サイト全体の統計</a></li>
      <li><a href="<?php echo url('api/openapi.json') ?>">OpenAPI 3.1仕様</a></li>
    </ul>
    <h2>共通仕様</h2>
    <p>一覧の<code>limit</code>は既定20、最大50です。次ページは<code>links.next</code>の署名付きcursorをそのまま利用してください。検索は20回/分、その他は60回/分です。</p>
    <p>レスポンスは<code>{data, meta, links}</code>形式です。GET・HEADのCORS、ETag、Last-Modifiedに対応し、CDNでは1時間キャッシュされます。</p>
    <h2>データとプライバシー</h2>
    <p>公開APIはサイトで表示している公開情報だけを返します。コメント、投稿者、ユーザーID、IPアドレス、LINE内部ID、招待URLの直値、管理・削除データは返しません。</p>
    <p><a href="<?php echo url('policy') ?>#methodology">取得方法・更新頻度・欠測の説明</a></p>
  </article>
</main>
<?php viewComponent('footer_inner') ?>
</body>
</html>
