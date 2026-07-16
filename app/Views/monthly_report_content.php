<!DOCTYPE html>
<html lang="ja">
<?php viewComponent('head', compact('_css', '_schema', 'canonical') + ['_meta' => $_meta->generateTags(), 'titleP' => true]) ?>
<body>
  <?php viewComponent('site_header', ['demoteTitle' => true]) ?>
  <main>
    <article class="terms">
      <h1><?php echo $month ?> オープンチャット月次データレポート</h1>
      <p>当サイトが公開情報を定期観測した結果では、掲載ルームは<?php echo number_format((int)($stats['room_count'] ?? 0)) ?>室、合計メンバー数は<?php echo number_format((int)($stats['total_members'] ?? 0)) ?>人です。</p>
      <p>集計時点：<time datetime="<?php echo \App\Services\PublicApi\PublicResourceFactory::dateToRfc3339($snapshot) ?>"><?php echo (new DateTimeImmutable($snapshot))->format('Y年n月j日 G:i') ?></time></p>

      <h2>主要指標</h2>
      <table>
        <tbody>
          <tr><th scope="row">掲載ルーム数</th><td><?php echo number_format((int)($stats['room_count'] ?? 0)) ?></td></tr>
          <tr><th scope="row">合計メンバー数</th><td><?php echo number_format((int)($stats['total_members'] ?? 0)) ?></td></tr>
          <tr><th scope="row">7日以内の新規掲載</th><td><?php echo number_format((int)($stats['new_rooms_7d'] ?? 0)) ?></td></tr>
        </tbody>
      </table>

      <h2>規模の大きいテーマ</h2>
      <ol>
        <?php foreach ($themes as $theme): ?>
          <li><a href="<?php echo $theme['canonical_url'] ?>"><?php echo $theme['tag'] ?></a> — <?php echo number_format($theme['room_count']) ?>室／<?php echo number_format($theme['total_members']) ?>人</li>
        <?php endforeach ?>
      </ol>

      <h2>24時間で伸びたルーム</h2>
      <ol>
        <?php foreach ($rankings as $room): ?>
          <li><a href="<?php echo $room['canonical_url'] ?>"><?php echo $room['name'] ?></a> — <?php echo signedNumF($room['changes']['twenty_four_hours'] ?? 0) ?>人</li>
        <?php endforeach ?>
      </ol>

      <h2>算出方法と関連データ</h2>
      <p>ルーム情報・人数・テーマを同じ公開Resourceから集計しています。欠測と制約は<a href="<?php echo url('policy') ?>#methodology">データの取得方法と算出方法</a>を参照してください。</p>
      <p><a href="<?php echo url('api/v1/stats') ?>">統計JSON API</a>／<a href="<?php echo url('api') ?>">API仕様</a></p>
    </article>
  </main>
  <?php viewComponent('footer_inner') ?>
  <script defer src="<?php echo fileUrl('/js/site_header_footer.js', urlRoot: '') ?>"></script>
</body>
</html>
