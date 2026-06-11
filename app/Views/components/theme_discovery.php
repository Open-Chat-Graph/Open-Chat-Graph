<?php

/**
 * テーマ発見セクション（/recommend 着地客の回遊導線）。
 * 🗂近いテーマ / 🚀急上昇 のタグチップ棚を表示する。
 *
 * データは ThemeDiscoveryService が確定し、コントローラから `_discovery` で渡る
 * （= フレームワークの自動エスケープ対象外＝RAW）。表示名はこの View で明示エスケープする。
 *
 * @var \App\Services\Recommend\Dto\ThemeDiscoveryDto $discovery
 */

$discovery = $discovery ?? null;
if (!$discovery || $discovery->isEmpty()) return;

$base = url('recommend/');
$chip = function (array $item, bool $hot = false) use ($base): void {
    $href = htmlspecialchars($base . $item['slug'], ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
    echo '<a class="theme-disco-chip' . ($hot ? ' theme-disco-chip--hot' : '') . '" href="' . $href . '">' . $name . '</a>';
};

// ページ文脈(現在テーマに近い棚)を先頭に置き、汎用棚(急上昇)は後ろで控えめに。
$nearbyLabel = $discovery->currentTagName !== ''
    ? sprintfT('「%s」の近いテーマ', $discovery->currentTagName)
    : ($discovery->nearbyCategoryName !== ''
        ? sprintfT('「%s」の近いテーマ', $discovery->nearbyCategoryName)
        : t('近いテーマ'));
$shelves = array_values(array_filter([
    ['hot' => false, 'icon' => '🗂', 'label' => $nearbyLabel, 'items' => $discovery->nearby],
    ['hot' => true,  'icon' => '🚀', 'label' => t('急上昇'), 'items' => $discovery->trending],
], fn($shelf) => !empty($shelf['items'])));
?>
<section class="theme-disco" aria-labelledby="theme-disco-title">
    <h2 class="theme-disco__title" id="theme-disco-title"><?php echo t('テーマを探す') ?></h2>

    <div class="theme-disco__shelves">
        <?php foreach ($shelves as $i => $shelf) : ?>
            <div class="theme-disco__shelf" style="--i:<?php echo (int)$i ?>">
                <div class="theme-disco__shelf-label"><span class="theme-disco__shelf-ico" aria-hidden="true"><?php echo $shelf['icon'] ?></span><?php echo htmlspecialchars((string)$shelf['label'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="theme-disco__chips"><?php foreach ($shelf['items'] as $item) $chip($item, $shelf['hot']); ?></div>
            </div>
        <?php endforeach ?>
    </div>

    <?php /* スタイルは style/components/theme_discovery.css に外部化済み。
             本コンポーネントを使うページはコントローラの $_css に
             'components/theme_discovery' を追加すること。 */ ?>
</section>
