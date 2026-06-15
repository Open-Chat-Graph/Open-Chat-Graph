<?php

/**
 * 分析文(narrative)セクション。
 * OcPageCacheGenerator が事前計算した「データ」(oc_page_cache.narrative_data) を受け取り、
 * /oc 表示時にレンダリングする（url() 等のURLヘルパーはリクエスト文脈で解決される）。
 *
 * - summary があれば分析本文を表示
 * - blog_link（Controller が状態から解決済み）があれば記事リンクを表示（summary 無しでもリンクだけ出る）
 * - narrative が null（生成不可）なら何も出さない
 *
 * @var array|null $narrative pattern / summary / detail / blog_link を含む
 */
?>
<?php if (!empty($narrative) && is_array($narrative)): ?>
  <section class="oc-narrative" aria-label="<?php echo t('オプチャグラフの分析') ?>">
    <?php if (!empty($narrative['summary'])): ?>
      <p class="oc-narrative__text"><span class="oc-narrative__badge" aria-hidden="true"><?php echo t('分析') ?></span><b class="oc-narrative__label"><?php echo h($narrative['summary']) ?></b><?php if (!empty($narrative['detail'])): ?><span class="oc-narrative__detail"><?php echo h($narrative['detail']) ?></span><?php endif ?></p>
    <?php endif ?>
    <?php // 分析が立てた疑問に答える記事へのインライン導線。解決は OcBlogContextLinkResolver（Controller）が行い、ここは描画のみ。 ?>
    <?php if (!empty($narrative['blog_link'])): ?>
      <a class="oc-narrative__article unset" href="<?php echo url('blog/' . $narrative['blog_link']['slug']) ?>"><span aria-hidden="true">📖</span> <?php echo h($narrative['blog_link']['label']) ?> →</a>
    <?php endif ?>
  </section>
<?php endif ?>
