<?php

/**
 * 分析文(narrative)セクション。
 * /oc 本体では描画せず、非同期 deferred-sections エンドポイント経由で生成・注入する
 * （高負荷対策: bot が叩く /oc 本体から narrative の SQLite 読み取りを外すため）。
 *
 * @var array|null $narrative
 * @var array $oc
 */
?>
<?php if (!empty($narrative) && is_array($narrative) && !empty($narrative['summary'])): ?>
  <section class="oc-narrative" aria-label="<?php echo t('オプチャグラフの分析') ?>">
    <p class="oc-narrative__text"><span class="oc-narrative__badge" aria-hidden="true"><?php echo t('分析') ?></span><b class="oc-narrative__label"><?php echo h($narrative['summary']) ?></b><?php if (!empty($narrative['detail'])): ?><span class="oc-narrative__detail"><?php echo h($narrative['detail']) ?></span><?php endif ?></p>
    <?php // 分析が立てた疑問に答える記事へのインライン導線（ja のみ・状態に合致したときだけ） ?>
    <?php viewComponent('oc_blog_context_link', ['pattern' => (string)($narrative['pattern'] ?? ''), 'member' => (int)($oc['member'] ?? 0)]) ?>
  </section>
<?php endif ?>
