<?php

/**
 * 「人気テーマ」内部リンク棚。recommend タグを合計人数順で動的に算出（popularThemes ヘルパ＝
 * cron 再生成の @tagList 由来）するため、ハードコード不要で自動更新され、改称/削除タグも自然に落ちる。
 * locale 別（@tagList が locale 別）。/recommend クラスタへ恒常的な内部リンクを供給する。
 *
 * @var bool $prominent  true=トップ本文用の強い見せ方 / false(既定)=フッター用のコンパクト表示
 */

$prominent = $prominent ?? false;
$_themes = popularThemes($prominent ? 16 : 12);
if (!$_themes) return;
?>
<?php if ($prominent): ?>
    <article class="popular-themes popular-themes--prominent">
        <h2 class="popular-themes-title"><?php echo t('人気テーマ') ?> <span aria-hidden="true">🔥</span></h2>
        <ul class="theme-chip-list">
            <?php foreach ($_themes as $_t) : ?>
                <li><a class="theme-chip" href="<?php echo url('recommend/' . $_t['slug']) ?>"><?php echo htmlspecialchars($_t['name'], ENT_QUOTES, 'UTF-8') ?></a></li>
            <?php endforeach ?>
        </ul>
    </article>
<?php else: ?>
    <section class="popular-themes">
        <div class="popular-themes-label"><?php echo t('人気テーマ') ?></div>
        <ul class="theme-chip-list">
            <?php foreach ($_themes as $_t) : ?>
                <li><a class="theme-chip" href="<?php echo url('recommend/' . $_t['slug']) ?>"><?php echo htmlspecialchars($_t['name'], ENT_QUOTES, 'UTF-8') ?></a></li>
            <?php endforeach ?>
        </ul>
    </section>
<?php endif ?>
