<?php

/**
 * ホームの「テーマを探す」検索（人数急増中のテーマの直下）。
 * 入力に応じて /oclist-tags（その語に一致する上位ルームが持つタグを集約）を呼び、
 * 関連テーマのチップを出して /recommend へ送客する軽量検索。棚は出さない（急増テーマは上に既出）。
 * slug はサーバで urlencode 済みなので再エンコードしない。
 */

$base = url('recommend/');
$api = url('oclist-tags');
?>
<section class="home-theme-search" aria-labelledby="home-theme-search-title">
    <h2 class="home-theme-search__title" id="home-theme-search-title"><?php echo t('テーマを探す') ?></h2>
    <div class="home-theme-search__box">
        <svg class="home-theme-search__icon" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 1 0-.7.7l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z" />
        </svg>
        <input id="home-theme-search-input" class="home-theme-search__input" type="search" inputmode="search" enterkeyhint="search"
            autocomplete="off" autocapitalize="off" spellcheck="false"
            placeholder="<?php echo t('テーマ名で検索') ?>" aria-label="<?php echo t('テーマ名で検索') ?>">
    </div>
    <div class="home-theme-search__results" id="home-theme-search-results" hidden></div>

    <script type="application/json" id="home-theme-search-data"><?php echo json_encode(
                                                                        [
                                                                            'base' => $base,
                                                                            'api' => $api,
                                                                            'nohit' => t('該当するテーマがありません'),
                                                                        ],
                                                                        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
                                                                    ) ?></script>

    <style>
        .home-theme-search {
            display: block;
            text-align: left;
            /* 周囲のコンテンツ（topic_tag 等）に合わせて左右16pxインセット */
            margin: 10px 16px 2px;
        }

        .home-theme-search__title {
            margin: 0 0 10px;
            padding: 0;
            display: flex;
            align-items: center;
            font-size: 14px;
            font-weight: 800;
            color: #06a34a;
            letter-spacing: .02em;
        }

        .home-theme-search__title::before {
            content: "";
            flex: 0 0 auto;
            width: 3px;
            height: 14px;
            background: #06c755;
            border-radius: 2px;
            margin-right: 8px;
        }

        .home-theme-search__box {
            position: relative;
            display: block;
            width: 100%;
        }

        .home-theme-search__icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            fill: #9aa3af;
            pointer-events: none;
        }

        /* font-size は必ず16px以上: iOS Safari のフォーカス時オートズーム回避 */
        .home-theme-search__input {
            display: block;
            width: 100%;
            box-sizing: border-box;
            height: 48px;
            margin: 0;
            padding: 0 14px 0 44px;
            font-size: 16px;
            color: #0f1620;
            background: #f6f8fa;
            border: 1.5px solid #e4e8ee;
            border-radius: 12px;
            outline: none;
            -webkit-appearance: none;
            appearance: none;
            transition: border-color .15s, background .15s, box-shadow .15s;
        }

        .home-theme-search__input::placeholder {
            color: #9aa3af;
        }

        .home-theme-search__input:focus {
            background: #fff;
            border-color: #06c755;
            box-shadow: 0 0 0 3px rgba(6, 199, 85, .14);
        }

        .home-theme-search__results {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .home-theme-search__results[hidden] {
            display: none;
        }

        .home-theme-search__chip {
            display: inline-flex;
            align-items: center;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            color: #067a37;
            background: #eefcf3;
            border: 1px solid #bfead0;
            border-radius: 8px;
            padding: 6px 14px;
            line-height: 1.2;
            -webkit-user-drag: none;
            transition: background .12s, border-color .12s, transform .08s;
        }

        .home-theme-search__chip:hover {
            background: #e2f9ea;
            border-color: #a6e0bd;
        }

        .home-theme-search__chip:active {
            transform: scale(.97);
        }

        .home-theme-search__empty {
            font-size: 13px;
            color: #9aa3af;
            padding: 4px 2px;
        }
    </style>

    <script>
        (() => {
            const input = document.getElementById('home-theme-search-input');
            const results = document.getElementById('home-theme-search-results');
            const cfg = document.getElementById('home-theme-search-data');
            if (!input || !results || !cfg) return;

            const {
                base,
                api,
                nohit
            } = JSON.parse(cfg.textContent);
            let timer = null;
            let token = 0; // 最新の検索のみ反映（レース対策）

            const chip = (name, slug) => {
                const a = document.createElement('a');
                a.className = 'home-theme-search__chip';
                a.href = base + slug; // slug はサーバで urlencode 済み
                a.textContent = name; // textContent ＝ XSS 不可
                a.draggable = false;
                return a;
            };

            const search = (q) => {
                const t = ++token;
                fetch(api + '?keyword=' + encodeURIComponent(q) + '&list=all&sort=member&order=desc&limit=20&page=0', {
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                    .then((r) => r.ok ? r.json() : [])
                    .then((tags) => {
                        if (t !== token) return; // 古い検索の結果は捨てる
                        if (Array.isArray(tags) && tags.length) {
                            results.replaceChildren(...tags.map((x) => chip(x.name, x.slug)));
                        } else {
                            const e = document.createElement('div');
                            e.className = 'home-theme-search__empty';
                            e.textContent = nohit;
                            results.replaceChildren(e);
                        }
                    })
                    .catch(() => {});
            };

            input.addEventListener('input', () => {
                const q = input.value.trim();
                clearTimeout(timer);
                if (!q) {
                    token++;
                    results.hidden = true;
                    results.replaceChildren();
                    return;
                }
                results.hidden = false;
                timer = setTimeout(() => search(q), 250);
            });

            // iOS Safari は submit を伴わないと Enter でキーボードが閉じないため明示 blur。
            // 変換確定中(IME composition / keyCode 229)の Enter は除外。
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.isComposing && e.keyCode !== 229) {
                    e.preventDefault();
                    input.blur();
                }
            });
        })();
    </script>
</section>
