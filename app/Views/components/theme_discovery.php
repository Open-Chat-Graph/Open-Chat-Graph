<?php

/**
 * テーマ発見セクション（/recommend 着地客の回遊導線）。
 * 全幅の検索（入力で全テーマを即時フィルタ）＋ 🚀急上昇 / 🔥人気 / 🗂近いカテゴリ の棚。
 *
 * データは ThemeDiscoveryService が確定し、コントローラから `_discovery` で渡る
 * （= フレームワークの自動エスケープ対象外＝RAW）。表示名はこの View で明示エスケープする。
 *
 * @var \App\Services\Recommend\Dto\ThemeDiscoveryDto $discovery
 */

$discovery = $discovery ?? null;
if (!$discovery || $discovery->isEmpty()) return;

$tdBase = url('recommend/');
$tdChip = function (array $item, bool $hot = false) use ($tdBase): void {
    $href = htmlspecialchars($tdBase . $item['slug'], ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
    echo '<a class="td-chip' . ($hot ? ' td-chip--hot' : '') . '" href="' . $href . '">' . $name . '</a>';
};

$tdShelves = array_values(array_filter([
    ['hot' => true,  'icon' => '🚀', 'label' => t('急上昇'), 'items' => $discovery->trending],
    ['hot' => false, 'icon' => '🔥', 'label' => t('人気'),   'items' => $discovery->popular],
    [
        'hot' => false,
        'icon' => '🗂',
        'label' => $discovery->nearbyCategoryName !== ''
            ? sprintfT('「%s」の近いテーマ', $discovery->nearbyCategoryName)
            : t('近いテーマ'),
        'items' => $discovery->nearby,
    ],
], fn($shelf) => !empty($shelf['items'])));
?>
<section class="td" aria-labelledby="td-title">
    <h2 class="td__title" id="td-title"><?php echo t('テーマを探す') ?></h2>

    <div class="td__searchwrap">
        <svg class="td__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 1 0-.7.7l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z" /></svg>
        <input id="td-input" class="td__input" type="search" inputmode="search" enterkeyhint="search"
            autocomplete="off" autocapitalize="off" spellcheck="false"
            placeholder="<?php echo t('テーマ名で検索') ?>" aria-label="<?php echo t('テーマ名で検索') ?>">
    </div>

    <div class="td__results" id="td-results" hidden></div>

    <div class="td__shelves" id="td-shelves">
        <?php foreach ($tdShelves as $i => $shelf) : ?>
            <div class="td__shelf" style="--i:<?php echo (int)$i ?>">
                <div class="td__shelf-label"><span class="td__shelf-ico" aria-hidden="true"><?php echo $shelf['icon'] ?></span><?php echo htmlspecialchars((string)$shelf['label'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="td__chips"><?php foreach ($shelf['items'] as $item) $tdChip($item, $shelf['hot']); ?></div>
            </div>
        <?php endforeach ?>
    </div>

    <script type="application/json" id="td-data"><?php echo json_encode(
        ['base' => $tdBase, 'nohit' => t('該当するテーマがありません'), 'tags' => $discovery->searchIndex],
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
    ) ?></script>

    <style>
        .td{margin:12px 0 4px;padding:18px 16px 20px;background:#fff;border:1px solid #e9ecf1;border-radius:16px}
        .td__title{all:unset;display:flex;align-items:center;font-size:15px;font-weight:700;color:#0f1620;letter-spacing:.02em;margin-bottom:12px}
        .td__title::before{content:"";display:inline-block;width:3px;height:15px;background:#06c755;border-radius:2px;margin-right:8px}
        .td__searchwrap{position:relative;display:flex;align-items:center}
        .td__icon{position:absolute;left:14px;width:20px;height:20px;fill:#9aa3af;pointer-events:none}
        /* font-size は必ず16px以上: iOS Safari のフォーカス時オートズーム回避 */
        .td__input{width:100%;box-sizing:border-box;height:48px;padding:0 14px 0 44px;font-size:16px;color:#0f1620;background:#f6f8fa;border:1.5px solid #e4e8ee;border-radius:12px;outline:none;-webkit-appearance:none;appearance:none;transition:border-color .15s,background .15s,box-shadow .15s}
        .td__input::placeholder{color:#9aa3af}
        .td__input:focus{background:#fff;border-color:#06c755;box-shadow:0 0 0 3px rgba(6,199,85,.14)}
        .td__input::-webkit-search-cancel-button{-webkit-appearance:none;height:18px;width:18px;background:#c4cad2;border-radius:50%;-webkit-mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z'/%3E%3C/svg%3E") center/contain no-repeat;mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z'/%3E%3C/svg%3E") center/contain no-repeat}
        .td__results{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
        .td__shelves{margin-top:16px;display:flex;flex-direction:column;gap:16px}
        .td__shelf{animation:td-fade .4s ease both;animation-delay:calc(var(--i,0)*60ms)}
        @keyframes td-fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
        @media (prefers-reduced-motion:reduce){.td__shelf{animation:none}}
        .td__shelf-label{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#5b6573;margin-bottom:9px}
        .td__shelf-ico{font-size:13px;line-height:1}
        .td__chips{display:flex;flex-wrap:wrap;gap:8px}
        .td-chip{display:inline-flex;align-items:center;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-decoration:none;font-size:14px;font-weight:500;color:#28303c;background:#f6f8fa;border:1px solid #e4e8ee;border-radius:999px;padding:8px 14px;line-height:1.15;transition:transform .08s ease,background .12s,border-color .12s}
        .td-chip:hover{background:#eef1f5;border-color:#d7dde6}
        .td-chip:active{transform:scale(.96)}
        .td-chip--hot{color:#067a37;background:#eefcf3;border-color:#bfead0;font-weight:700}
        .td-chip--hot:hover{background:#e2f9ea;border-color:#a6e0bd}
        .td__empty{font-size:13px;color:#9aa3af;padding:6px 2px}
    </style>
    <script>
        (function () {
            var input = document.getElementById('td-input'),
                results = document.getElementById('td-results'),
                shelves = document.getElementById('td-shelves'),
                cfg = document.getElementById('td-data');
            if (!input || !results || !shelves || !cfg) return;
            var data = JSON.parse(cfg.textContent);
            function run(q) {
                q = q.trim().toLowerCase();
                if (!q) { results.hidden = true; results.textContent = ''; shelves.hidden = false; return; }
                shelves.hidden = true; results.hidden = false; results.textContent = '';
                var frag = document.createDocumentFragment(), n = 0;
                for (var i = 0; i < data.tags.length && n < 60; i++) {
                    var name = data.tags[i][0];
                    if (name.toLowerCase().indexOf(q) < 0) continue;
                    var a = document.createElement('a');
                    a.className = 'td-chip';
                    a.href = data.base + data.tags[i][1];
                    a.textContent = name;
                    frag.appendChild(a);
                    n++;
                }
                if (!n) {
                    var e = document.createElement('div');
                    e.className = 'td__empty';
                    e.textContent = data.nohit;
                    results.appendChild(e);
                    return;
                }
                results.appendChild(frag);
            }
            input.addEventListener('input', function () { run(input.value); });
        })();
    </script>
</section>
