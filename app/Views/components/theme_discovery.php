<?php

use App\Config\AppConfig;
use App\Services\Recommend\TagDefinition\Ja\RecommendUtility;
use Shared\MimimalCmsConfig;

/**
 * テーマ発見セクション（/recommend 着地客の回遊導線）。
 * 検索ボックス（全テーマを即時フィルタ＝唯一の全件アクセス）＋ 🚀急上昇 / 🔥人気 / 🗂近いカテゴリ の棚。
 *
 * @var array $tagList    カテゴリ別グループのタグ行（StaticDataFile::getTagList）
 * @var string $tag       現在ページのタグ（canonical）
 * @var \App\Services\StaticData\Dto\StaticTopPageDto $topPageDto 急上昇テーマ供給元
 */

$tdCurrent = $tag ?? '';
$tdTagList = $tagList ?? [];
if (!is_array($tdTagList) || !$tdTagList) return;

$tdCatNames = array_flip(AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] ?? []); // id => name

// 全タグを平坦化（tag => row）し、現在タグのカテゴリを特定
$tdAll = [];
$tdCurrentCat = null;
foreach ($tdTagList as $catId => $rows) {
    if (!is_array($rows)) continue;
    foreach ($rows as $r) {
        $t = $r['tag'] ?? '';
        if ($t === '') continue;
        if (!isset($tdAll[$t])) $tdAll[$t] = $r + ['category' => $catId];
        if ($t === $tdCurrent) $tdCurrentCat = $catId;
    }
}
unset($tdAll[$tdCurrent]);
if (!$tdAll) return;

$tdChip = function (string $canonical, string $cls = '') {
    $href = url('recommend/' . urlencode(htmlspecialchars_decode($canonical)));
    $label = RecommendUtility::extractTag($canonical);
    echo '<a class="theme-disco__chip ' . $cls . '" href="' . $href . '">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
};

// 🚀 急上昇（topPageDto の hour / hour24 から、現在タグを除外）
$tdTrendSet = [];
$tp = $topPageDto->recommendList ?? [];
foreach (['hour', 'hour24'] as $k) {
    foreach (($tp[$k] ?? []) as $w) {
        if ($w === '' || $w === $tdCurrent || isset($tdTrendSet[$w])) continue;
        $tdTrendSet[$w] = true;
        if (count($tdTrendSet) >= 14) break 2;
    }
}
$tdTrend = array_keys($tdTrendSet);

// 🔥 人気（合計人数順、急上昇に出たものは除外）
$tdPop = $tdAll;
uasort($tdPop, fn($a, $b) => ($b['total_member'] ?? 0) <=> ($a['total_member'] ?? 0));
$tdPopList = [];
foreach (array_keys($tdPop) as $t) {
    if (isset($tdTrendSet[$t])) continue;
    $tdPopList[] = $t;
    if (count($tdPopList) >= 12) break;
}

// 🗂 近いカテゴリ（現在タグと同カテゴリ、合計人数順）
$tdCatList = [];
$tdCatName = $tdCurrentCat !== null ? ($tdCatNames[$tdCurrentCat] ?? '') : '';
if ($tdCurrentCat !== null) {
    $catRows = array_filter($tdAll, fn($r) => ($r['category'] ?? null) === $tdCurrentCat);
    uasort($catRows, fn($a, $b) => ($b['total_member'] ?? 0) <=> ($a['total_member'] ?? 0));
    foreach (array_keys($catRows) as $t) {
        $tdCatList[] = $t;
        if (count($tdCatList) >= 12) break;
    }
}

// 検索インデックス: [表示名, urlスラッグ]
$tdSearch = [];
foreach (array_keys($tdAll) as $t) {
    $tdSearch[] = [RecommendUtility::extractTag($t), rawurlencode(htmlspecialchars_decode($t))];
}
$tdBase = url('recommend/');
?>
<section class="theme-disco" aria-labelledby="theme-disco-title">
    <h2 class="theme-disco__title" id="theme-disco-title"><?php echo t('ほかのテーマを探す') ?></h2>

    <div class="theme-disco__search">
        <svg class="theme-disco__search-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 1 0-.7.7l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"/></svg>
        <input type="search" class="theme-disco__input" id="theme-disco-input" autocomplete="off"
            placeholder="<?php echo t('テーマ名で検索') ?>" aria-label="<?php echo t('テーマ名で検索') ?>">
    </div>

    <div class="theme-disco__results" id="theme-disco-results" hidden></div>

    <div class="theme-disco__shelves" id="theme-disco-shelves">
        <?php if ($tdTrend) : ?>
            <div class="theme-disco__shelf">
                <div class="theme-disco__shelf-h">🚀&nbsp;<?php echo t('急上昇') ?></div>
                <div class="theme-disco__chips"><?php foreach ($tdTrend as $w) $tdChip($w, 'is-trend'); ?></div>
            </div>
        <?php endif ?>
        <?php if ($tdPopList) : ?>
            <div class="theme-disco__shelf">
                <div class="theme-disco__shelf-h">🔥&nbsp;<?php echo t('人気') ?></div>
                <div class="theme-disco__chips"><?php foreach ($tdPopList as $w) $tdChip($w); ?></div>
            </div>
        <?php endif ?>
        <?php if ($tdCatList) : ?>
            <div class="theme-disco__shelf">
                <div class="theme-disco__shelf-h">🗂&nbsp;<?php echo $tdCatName !== '' ? sprintfT('「%s」の近いテーマ', $tdCatName) : t('近いテーマ') ?></div>
                <div class="theme-disco__chips"><?php foreach ($tdCatList as $w) $tdChip($w); ?></div>
            </div>
        <?php endif ?>
    </div>

    <script type="application/json" id="theme-disco-data"><?php echo json_encode(['base' => $tdBase, 'nohit' => t('該当するテーマがありません'), 'tags' => $tdSearch], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?></script>

    <style>
        .theme-disco{margin:8px 0 4px;padding:16px 16px 18px;background:#f7f8fa;border-radius:14px}
        .theme-disco__title{all:unset;display:block;font-size:15px;font-weight:bold;color:#111;margin-bottom:10px}
        .theme-disco__search{display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #e2e5ea;border-radius:10px;padding:0 12px;height:42px}
        .theme-disco__search:focus-within{border-color:#06c755}
        .theme-disco__search-ico{width:18px;height:18px;fill:#9aa1ab;flex:0 0 auto}
        .theme-disco__input{all:unset;flex:1;font-size:15px;line-height:42px;color:#111;min-width:0}
        .theme-disco__shelves{margin-top:14px;display:flex;flex-direction:column;gap:14px}
        .theme-disco__shelf-h{font-size:12.5px;font-weight:bold;color:#555;margin-bottom:7px}
        .theme-disco__chips{display:flex;flex-wrap:wrap;gap:7px}
        .theme-disco__results{margin-top:12px;display:flex;flex-wrap:wrap;gap:7px;min-height:38px}
        .theme-disco__chip{display:inline-flex;align-items:center;text-decoration:none;font-size:13.5px;color:#1f2937;background:#fff;border:1px solid #e2e5ea;border-radius:999px;padding:7px 13px;line-height:1.2;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .theme-disco__chip:active{background:#eef0f3}
        .theme-disco__chip.is-trend{border-color:#bdeccf;background:#f0fbf4;color:#06863c;font-weight:bold}
        .theme-disco__empty{font-size:13px;color:#9aa1ab;padding:8px 2px}
    </style>
    <script>
        (function () {
            var el = document.getElementById('theme-disco-input');
            var res = document.getElementById('theme-disco-results');
            var shelves = document.getElementById('theme-disco-shelves');
            var cfg = document.getElementById('theme-disco-data');
            if (!el || !res || !shelves || !cfg) return;
            var data = JSON.parse(cfg.textContent);
            var render = function (q) {
                q = q.trim().toLowerCase();
                if (!q) { res.hidden = true; res.innerHTML = ''; shelves.hidden = false; return; }
                shelves.hidden = true; res.hidden = false;
                var out = [], n = 0;
                for (var i = 0; i < data.tags.length && n < 60; i++) {
                    var name = data.tags[i][0];
                    if (name.toLowerCase().indexOf(q) === -1) continue;
                    var a = document.createElement('a');
                    a.className = 'theme-disco__chip';
                    a.href = data.base + data.tags[i][1];
                    a.textContent = name;
                    out.push(a); n++;
                }
                res.innerHTML = '';
                if (!out.length) { var e = document.createElement('div'); e.className = 'theme-disco__empty'; e.textContent = data.nohit; res.appendChild(e); return; }
                out.forEach(function (a) { res.appendChild(a); });
            };
            el.addEventListener('input', function () { render(el.value); });
        })();
    </script>
</section>
