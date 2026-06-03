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
        [
            'base' => $base,
            'nohit' => t('該当するテーマがありません'),
            'tags' => $discovery->searchIndex,
            'ocApi' => url('oclist'),
            'ocTagsApi' => url('oclist-tags'),
            'ocRoom' => url('oc'),
            'roomsHeading' => t('一致するオープンチャット'),
            'noResultAll' => t('該当するテーマ・ルームがありません'),
            'memberFmt' => t('メンバー %s人'),
            'increaseFmt' => t('%s人増加'),
        ],
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
    ) ?></script>

    <style>
        /* サイトのグローバル section{display:flex;justify-content:center} を打ち消す（既存 .recommend-ranking-section と同様） */
        .theme-disco{display:block;text-align:left;margin-top:10px;padding:18px 1rem 6px;border-top:1px solid #eef0f3}
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
        /* テーマ0件時のフォールバック（一致するオープンチャット＝部屋。チップでなくリスト行で“部屋”と分かる見せ方） */
        .theme-disco__rooms{display:block;width:100%}
        .theme-disco__note{font-size:13px;color:#9aa3af;padding:2px 2px 0}
        .theme-disco__rooms-h{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#5b6573;margin:10px 0 8px}
        .theme-disco__rooms-h::before{content:"💬";font-size:12px}
        .theme-disco__room{display:flex;flex-direction:column;gap:2px;padding:9px 11px;margin-bottom:8px;background:#fff;border:1px solid #eef0f3;border-radius:10px;text-decoration:none}
        .theme-disco__room:active{background:#f6f8fa}
        .theme-disco__room-name{font-size:14px;font-weight:600;color:#1f2937;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .theme-disco__room-meta{font-size:12px;color:#5b6573}
    </style>
    <script>
        (() => {
            const input = document.getElementById('theme-disco-input');
            const results = document.getElementById('theme-disco-results');
            const shelves = document.getElementById('theme-disco-shelves');
            const cfg = document.getElementById('theme-disco-data');
            if (!input || !results || !shelves || !cfg) return;

            const { base, nohit, tags, ocApi, ocTagsApi, ocRoom, roomsHeading, noResultAll, memberFmt, increaseFmt } = JSON.parse(cfg.textContent);
            const MAX = 60;
            let reqToken = 0;          // フォールバック取得のレース対策（最新の検索のみ反映）
            let debounceTimer = null;

            // 検索の正規化: 半角全角(NFKC)・大文字小文字(toLowerCase)・カタカナ/ひらがな(カナ→ひら) を無視して一致させる。
            const norm = (s) => String(s).normalize('NFKC').toLowerCase()
                .replace(/[ァ-ヶ]/g, (ch) => String.fromCharCode(ch.charCodeAt(0) - 0x60));
            const normTags = tags.map((t) => norm(t[0])); // タグ表示名の正規化済みインデックス（初回のみ計算）

            const chip = ([name, slug]) => {
                const a = document.createElement('a');
                a.className = 'theme-disco-chip';
                a.href = base + slug;        // slug はサーバで urlencode 済み（javascript: 等は混入不可）
                a.textContent = name;        // textContent なので XSS 不可
                return a;
            };

            const fmt = (tpl, v) => String(tpl).replace('%s', v);

            // テーマ0件時のフォールバック行（オープンチャット＝部屋）。textContent のみ＝XSS不可、href は /oc/<int id>。
            const roomRow = (r) => {
                const a = document.createElement('a');
                a.className = 'theme-disco__room';
                a.href = ocRoom + '/' + encodeURIComponent(r.id);
                const name = document.createElement('span');
                name.className = 'theme-disco__room-name';
                name.textContent = r.name;
                const meta = document.createElement('span');
                meta.className = 'theme-disco__room-meta';
                meta.textContent = fmt(memberFmt, Number(r.member || 0).toLocaleString());
                const inc = Number(r.increasedMember || 0);
                if (inc > 0) {
                    const pos = document.createElement('span');
                    pos.className = 'positive';
                    const stat = document.createElement('span');
                    stat.className = 'openchat-item-stats';
                    stat.textContent = ' ・ ' + fmt(increaseFmt, inc.toLocaleString());
                    pos.appendChild(stat);
                    meta.appendChild(pos);
                }
                a.append(name, meta);
                return a;
            };

            const getJson = (url) => fetch(url, { headers: { 'Accept': 'application/json' } })
                .then((res) => res.ok ? res.json() : null)
                .then((data) => Array.isArray(data) ? data : null)
                .catch(() => null);

            // キーワードに一致する部屋: 24時間の増加TOP3 → 0件なら全体(member順)TOP3。
            const fetchRooms = async (q) => {
                const enc = encodeURIComponent(q);
                let rooms = await getJson(ocApi + '?keyword=' + enc + '&list=daily&sort=increase&order=desc&limit=3');
                if (!rooms || !rooms.length) {
                    rooms = await getJson(ocApi + '?keyword=' + enc + '&list=all&sort=member&order=desc&limit=3');
                }
                return rooms || [];
            };

            const showRoomFallback = (q) => {
                const token = ++reqToken;
                fetchRooms(q).then((rooms) => {
                    if (token !== reqToken) return; // 古い検索の結果は捨てる
                    if (!rooms.length) {
                        const empty = document.createElement('div');
                        empty.className = 'theme-disco__empty';
                        empty.textContent = noResultAll;
                        results.replaceChildren(empty);
                        return;
                    }
                    const box = document.createElement('div');
                    box.className = 'theme-disco__rooms';
                    const note = document.createElement('div');
                    note.className = 'theme-disco__note';
                    note.textContent = nohit;           // 一致するテーマはありません（フォールバックである旨）
                    const head = document.createElement('div');
                    head.className = 'theme-disco__rooms-h';
                    head.textContent = roomsHeading;     // 一致するオープンチャット（＝部屋）
                    box.append(note, head, ...rooms.map(roomRow));
                    results.replaceChildren(box);
                });
            };

            // タグ優先フォールバック: テーマ名が直接一致しなくても、一致する部屋が持つタグを表示する。
            // タグが1つも無いときだけ部屋を表示する（ほぼ全キーワードで何かしらタグが出る）。
            const showTagFallback = (q) => {
                const token = ++reqToken;
                getJson(ocTagsApi + '?keyword=' + encodeURIComponent(q) + '&list=all&sort=member&order=desc&limit=20')
                    .then((tagItems) => {
                        if (token !== reqToken) return;   // 古い検索の結果は捨てる
                        if (tagItems && tagItems.length) {
                            results.replaceChildren(...tagItems.map((it) => chip([it.name, it.slug])));
                        } else {
                            showRoomFallback(q);          // タグ0件のときだけ部屋
                        }
                    });
            };

            const render = (raw) => {
                const q = raw.trim();
                if (!q) {
                    clearTimeout(debounceTimer);
                    reqToken++;
                    results.hidden = true;
                    results.replaceChildren();
                    shelves.hidden = false;
                    return;
                }
                shelves.hidden = true;
                results.hidden = false;

                const nq = norm(q);
                const hits = [];
                for (let i = 0; i < tags.length; i++) {
                    if (normTags[i].includes(nq)) {
                        hits.push(chip(tags[i]));
                        if (hits.length >= MAX) break;
                    }
                }
                if (hits.length) {
                    clearTimeout(debounceTimer);
                    reqToken++;                          // 進行中のフォールバック取得を無効化
                    results.replaceChildren(...hits);
                    return;
                }
                // テーマ名の直接一致なし → まず「一致する部屋が持つタグ」を表示（タグ優先）。無ければ部屋。
                results.replaceChildren();
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => showTagFallback(q), 250);
            };

            // 検索状態をセッションに保存し、ブラウザバック時に復元する。
            // （チップで他テーマへ遷移→戻る、で検索語が消えて使いづらい問題への対処）
            const STORE_KEY = 'themeDiscoQ:' + location.pathname;
            const save = (q) => {
                try { q ? sessionStorage.setItem(STORE_KEY, q) : sessionStorage.removeItem(STORE_KEY); } catch (e) { /* private mode 等は無視 */ }
            };
            const restore = () => {
                let q = '';
                try { q = sessionStorage.getItem(STORE_KEY) || ''; } catch (e) { /* noop */ }
                if (q && !input.value) input.value = q;
                if (input.value) render(input.value);
            };

            input.addEventListener('input', () => { render(input.value); save(input.value); });

            // iOS Safari は <form> の submit を伴わないと Enter でキーボード(IME)が閉じない。
            // 変換確定中(IME composition)の Enter は「確定」用なので除外し、確定後の Enter で
            // 明示的に blur してソフトキーボードを閉じる（keyCode 229 = 変換中の保険）。
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.isComposing && e.keyCode !== 229) {
                    e.preventDefault();
                    input.blur();
                }
            });

            // 戻る/進む（bfcache無効＝no-store時も含む）でのみ復元。通常遷移や別テーマでは復元しない。
            const nav = performance.getEntriesByType?.('navigation')?.[0];
            const isBackForward = nav ? nav.type === 'back_forward' : performance.navigation?.type === 2;
            if (isBackForward) restore();
            window.addEventListener('pageshow', (e) => { if (e.persisted) restore(); });
        })();
    </script>
</section>
