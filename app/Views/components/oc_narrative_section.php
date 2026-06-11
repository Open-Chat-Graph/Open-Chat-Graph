<?php

/**
 * 分析文(narrative)セクション。
 * OcPageCacheGenerator が事前計算した「データ」(oc_page_cache.narrative_data) を受け取り、
 * /oc 表示時にレンダリングする（url() 等のURLヘルパーはリクエスト文脈で解決される）。
 *
 * - summary があれば分析本文 + 状態に合った記事リンク
 * - summary が無くても pattern があれば記事リンクだけ出す（「空ならブログリンクだけ」）
 * - narrative が null（生成不可）なら何も出さない
 *
 * @var array|null $narrative
 * @var array $oc
 */
?>
<?php if (!empty($narrative) && is_array($narrative)): ?>
  <section class="oc-narrative" aria-label="<?php echo t('オプチャグラフの分析') ?>">
    <?php if (!empty($narrative['summary'])): ?>
      <p class="oc-narrative__text"><span class="oc-narrative__badge" aria-hidden="true"><?php echo t('分析') ?></span><b class="oc-narrative__label"><?php echo h($narrative['summary']) ?></b><?php if (!empty($narrative['detail'])): ?><span class="oc-narrative__detail"><?php echo h($narrative['detail']) ?></span><?php endif ?></p>
    <?php endif ?>
    <?php // 分析が立てた疑問に答える記事へのインライン導線（ja のみ・状態に合致したときだけ） ?>
    <?php viewComponent('oc_blog_context_link', ['pattern' => (string)($narrative['pattern'] ?? ''), 'member' => (int)($oc['member'] ?? 0)]) ?>
  </section>
<?php endif ?>
