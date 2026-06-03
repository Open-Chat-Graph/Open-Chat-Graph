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

$base = url('recommend/');
$chip = function (array $item, bool $hot = false) use ($base): void {
    $href = htmlspecialchars($base . $item['slug'], ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
    echo '<a class="theme-disco-chip' . ($hot ? ' theme-disco-chip--hot' : '') . '" href="' . $href . '">' . $name . '</a>';
};

$shelves = array_values(array_filter([
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
<section class="theme-disco" aria-labelledby="theme-disco-title">
    <h2 class="theme-disco__title" id="theme-disco-title"><?php echo t('テーマを探す') ?></h2>

    <div class="theme-disco__search">
        <svg class="theme-disco__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 1 0-.7.7l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z" /></svg>
        <input id="theme-disco-input" class="theme-disco__input" type="search" inputmode="search" enterkeyhint="search"
            autocomplete="off" autocapitalize="off" spellcheck="false"
            placeholder="<?php echo t('テーマ名で検索') ?>" aria-label="<?php echo t('テーマ名で検索') ?>">
    </div>

    <div class="theme-disco__results" id="theme-disco-results" hidden></div>

    <div class="theme-disco__shelves" id="theme-disco-shelves">
        <?php foreach ($shelves as $i => $shelf) : ?>
            <div class="theme-disco__shelf" style="--i:<?php echo (int)$i ?>">
                <div class="theme-disco__shelf-label"><span class="theme-disco__shelf-ico" aria-hidden="true"><?php echo $shelf['icon'] ?></span><?php echo htmlspecialchars((string)$shelf['label'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="theme-disco__chips"><?php foreach ($shelf['items'] as $item) $chip($item, $shelf['hot']); ?></div>
            </div>
        <?php endforeach ?>
    </div>

    <script type="application/json" id="theme-disco-data"><?php echo json_encode(
        ['base' => $base, 'nohit' => t('該当するテーマがありません'), 'tags' => $discovery->searchIndex],
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
    ) ?></script>

    <style>
        .theme-disco{margin-top:10px;padding:18px 1rem 6px;border-top:1px solid #eef0f3}
        .theme-disco__title{margin:0 0 12px;padding:0;display:flex;align-items:center;font-size:15px;font-weight:700;color:#0f1620;letter-spacing:.02em}
        .theme-disco__title::before{content:"";flex:0 0 auto;width:3px;height:15px;background:#06c755;border-radius:2px;margin-right:8px}
        .theme-disco__search{position:relative;display:block;width:100%}
        .theme-disco__icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);width:20px;height:20px;fill:#9aa3af;pointer-events:none}
        /* font-size は必ず16px以上: iOS Safari のフォーカス時オートズーム回避 */
        .theme-disco__input{display:block;width:100%;box-sizing:border-box;height:48px;margin:0;padding:0 14px 0 44px;font-size:16px;color:#0f1620;background:#f6f8fa;border:1.5px solid #e4e8ee;border-radius:12px;outline:none;-webkit-appearance:none;appearance:none;transition:border-color .15s,background .15s,box-shadow .15s}
        .theme-disco__input::placeholder{color:#9aa3af}
        .theme-disco__input:focus{background:#fff;border-color:#06c755;box-shadow:0 0 0 3px rgba(6,199,85,.14)}
        .theme-disco__results{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
        .theme-disco__shelves{display:flex;flex-direction:column;gap:16px;margin-top:16px}
        /* [hidden] を確実に優先（display:flex に勝たせる） */
        .theme-disco__results[hidden],.theme-disco__shelves[hidden]{display:none}
        .theme-disco__shelf{animation:theme-disco-fade .4s ease both;animation-delay:calc(var(--i,0)*60ms)}
        @keyframes theme-disco-fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
        @media (prefers-reduced-motion:reduce){.theme-disco__shelf{animation:none}}
        .theme-disco__shelf-label{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#5b6573;margin-bottom:9px}
        .theme-disco__shelf-ico{font-size:13px;line-height:1}
        .theme-disco__chips{display:flex;flex-wrap:wrap;gap:8px}
        .theme-disco-chip{display:inline-flex;align-items:center;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-decoration:none;font-size:14px;font-weight:500;color:#28303c;background:#f6f8fa;border:1px solid #e4e8ee;border-radius:999px;padding:8px 14px;line-height:1.15;transition:transform .08s ease,background .12s,border-color .12s}
        .theme-disco-chip:hover{background:#eef1f5;border-color:#d7dde6}
        .theme-disco-chip:active{transform:scale(.96)}
        .theme-disco-chip--hot{color:#067a37;background:#eefcf3;border-color:#bfead0;font-weight:700}
        .theme-disco-chip--hot:hover{background:#e2f9ea;border-color:#a6e0bd}
        .theme-disco__empty{font-size:13px;color:#9aa3af;padding:6px 2px}
    </style>
    <script>
        (() => {
            const input = document.getElementById('theme-disco-input');
            const results = document.getElementById('theme-disco-results');
            const shelves = document.getElementById('theme-disco-shelves');
            const cfg = document.getElementById('theme-disco-data');
            if (!input || !results || !shelves || !cfg) return;

            const { base, nohit, tags } = JSON.parse(cfg.textContent);
            const MAX = 60;

            const chip = ([name, slug]) => {
                const a = document.createElement('a');
                a.className = 'theme-disco-chip';
                a.href = base + slug;        // slug はサーバで urlencode 済み（javascript: 等は混入不可）
                a.textContent = name;        // textContent なので XSS 不可
                return a;
            };

            const render = (raw) => {
                const q = raw.trim().toLowerCase();
                if (!q) {
                    results.hidden = true;
                    results.replaceChildren();
                    shelves.hidden = false;
                    return;
                }
                shelves.hidden = true;
                results.hidden = false;

                const hits = [];
                for (const tag of tags) {
                    if (tag[0].toLowerCase().includes(q)) {
                        hits.push(chip(tag));
                        if (hits.length >= MAX) break;
                    }
                }
                if (hits.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'theme-disco__empty';
                    empty.textContent = nohit;
                    results.replaceChildren(empty);
                    return;
                }
                results.replaceChildren(...hits);
            };

            input.addEventListener('input', () => render(input.value));
        })();
    </script>
</section>
