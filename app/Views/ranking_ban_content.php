<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php

use Shadow\Kernel\Reception as R;

viewComponent('head', compact('_css', '_meta')) ?>

<body class="body">
    <!-- 固定ヘッダー -->
    <?php viewComponent('site_header', compact('_updatedAt')) ?>
    <main style="max-width: 812px; padding: 0 1rem; overflow: hidden;">
        <header class="openchat-list-title-area unset" style="padding-top: 1rem;">
            <div style="flex-direction: column;">
                <h2 class="list-title">
                    オプチャ公式ランキング掲載の分析
                </h2>
                <p class="rb-lead">
                    公式ランキングから<b>消えた部屋（未掲載）</b>と、<b>消えたあと復活した部屋（再掲載済み）</b>を毎時記録しています。「<b>内容を変更した直後に消えたのか</b>」で絞り込めて、検索落ち・圏外の原因切り分けに使えます。
                </p>
            </div>
        </header>

        <!-- 絞り込みの主役: プリセット＋キーワード。細かい条件は下の「詳細設定」(普段は閉じる) -->
        <section class="rb-presets-outer">
            <h3 class="rb-presets-title">よく使う絞り込み</h3>
            <div class="rb-presets" role="group" aria-label="よく使う絞り込み">
                <button type="button" class="rb-preset<?php if (R::input('publish') === 1 && R::input('change') === 0 && R::input('percent') === 50) echo ' is-active' ?>" data-publish="1" data-change="0" data-percent="50">内容変更の直後に消えた部屋</button>
                <button type="button" class="rb-preset<?php if (R::input('publish') === 1 && R::input('change') === 1 && R::input('percent') === 50) echo ' is-active' ?>" data-publish="1" data-change="1" data-percent="50">変更していないのに消えた部屋</button>
                <button type="button" class="rb-preset<?php if (R::input('publish') === 0 && R::input('change') === 2 && R::input('percent') === 50) echo ' is-active' ?>" data-publish="0" data-change="2" data-percent="50">消えたあと復活した部屋</button>
                <button type="button" class="rb-preset<?php if (R::input('publish') === 2 && R::input('change') === 2 && R::input('percent') === 100) echo ' is-active' ?>" data-publish="2" data-change="2" data-percent="100">すべての記録を見る</button>
            </div>
            <div class="rb-search" role="search">
                <input type="search" id="rb-keyword" name="keyword" placeholder="ルーム名・説明文で検索（Enterで反映）" aria-label="キーワードで絞り込み（ルーム名・説明文）" autocomplete="off" value="<?php echo R::has('keyword') ? h(R::input('keyword')) : '' ?>">
                <button type="button" id="rb-keyword-clear" class="rb-search-clear<?php if (!R::has('keyword') || R::input('keyword') === '') echo ' is-hidden' ?>" aria-label="キーワードを消去">&#10005;</button>
            </div>
        </section>

        <?php // 現在の条件がプリセットに無い組み合わせなら、詳細設定を開いた状態で出す（効いている条件を隠さない） ?>
        <?php $rbIsPreset = $since === '' && $until === ''
            && in_array([R::input('publish'), R::input('change'), R::input('percent')], [[1, 0, 50], [1, 1, 50], [0, 2, 50], [2, 2, 100]], true); ?>
        <!-- 詳細設定（プロ向け・普段は閉じる） -->
        <details class="rb-advanced" id="rb-advanced"<?php if (!$rbIsPreset) echo ' open' ?>>
            <summary>詳細設定<span class="rb-advanced-sub">掲載状況・変更の有無・順位を個別に指定</span></summary>
            <div class="rb-panel" aria-label="詳細の絞り込み条件">
            <div class="rb-field">
                <div class="rb-field-label" id="rb-label-publish">掲載状況</div>
                <div class="rb-field-control">
                    <div class="rb-seg" role="radiogroup" aria-labelledby="rb-label-publish">
                        <label class="rb-seg-item<?php if (R::input('publish') === 1) echo ' is-selected' ?>">
                            <input type="radio" name="publish" value="1" <?php if (R::input('publish') === 1) echo 'checked' ?>>
                            <span class="rb-seg-text"><span class="rb-dot rb-dot--gone" aria-hidden="true"></span>消えたまま</span>
                        </label>
                        <label class="rb-seg-item<?php if (R::input('publish') === 0) echo ' is-selected' ?>">
                            <input type="radio" name="publish" value="0" <?php if (R::input('publish') === 0) echo 'checked' ?>>
                            <span class="rb-seg-text"><span class="rb-dot rb-dot--back" aria-hidden="true"></span>復活した</span>
                        </label>
                        <label class="rb-seg-item<?php if (R::input('publish') === 2) echo ' is-selected' ?>">
                            <input type="radio" name="publish" value="2" <?php if (R::input('publish') === 2) echo 'checked' ?>>
                            <span class="rb-seg-text">すべて</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="rb-field">
                <div class="rb-field-label" id="rb-label-change">ルーム内容の変更</div>
                <div class="rb-field-control">
                    <div class="rb-seg" role="radiogroup" aria-labelledby="rb-label-change">
                        <label class="rb-seg-item<?php if (R::input('change') === 0) echo ' is-selected' ?>">
                            <input type="radio" name="change" value="0" <?php if (R::input('change') === 0) echo 'checked' ?>>
                            <span class="rb-seg-text">変更あり</span>
                        </label>
                        <label class="rb-seg-item<?php if (R::input('change') === 1) echo ' is-selected' ?>">
                            <input type="radio" name="change" value="1" <?php if (R::input('change') === 1) echo 'checked' ?>>
                            <span class="rb-seg-text">変更なし</span>
                        </label>
                        <label class="rb-seg-item<?php if (R::input('change') === 2) echo ' is-selected' ?>">
                            <input type="radio" name="change" value="2" <?php if (R::input('change') === 2) echo 'checked' ?>>
                            <span class="rb-seg-text">すべて</span>
                        </label>
                    </div>
                    <p class="rb-field-hint">消える直前に名前・説明文・画像などを変えていたか（変更なし判定は2025年8月11日以降の記録が対象）</p>
                </div>
            </div>
            <div class="rb-field">
                <div class="rb-field-label" id="rb-label-percent">最後に載っていた順位</div>
                <div class="rb-field-control">
                    <div class="rb-seg" role="radiogroup" aria-labelledby="rb-label-percent">
                        <label class="rb-seg-item<?php if (R::input('percent') === 50) echo ' is-selected' ?>">
                            <input type="radio" name="percent" value="50" <?php if (R::input('percent') === 50) echo 'checked' ?>>
                            <span class="rb-seg-text">上位50%だけ</span>
                        </label>
                        <label class="rb-seg-item<?php if (R::input('percent') === 80) echo ' is-selected' ?>">
                            <input type="radio" name="percent" value="80" <?php if (R::input('percent') === 80) echo 'checked' ?>>
                            <span class="rb-seg-text">上位80%まで</span>
                        </label>
                        <label class="rb-seg-item<?php if (R::input('percent') === 100) echo ' is-selected' ?>">
                            <input type="radio" name="percent" value="100" <?php if (R::input('percent') === 100) echo 'checked' ?>>
                            <span class="rb-seg-text">すべて</span>
                        </label>
                    </div>
                    <p class="rb-field-hint">下位の部屋は単純な圏外落ちが多いので除外できます</p>
                </div>
            </div>
            <div class="rb-field">
                <div class="rb-field-label" id="rb-label-period">消えた時期</div>
                <div class="rb-field-control">
                    <div class="rb-dates" role="group" aria-labelledby="rb-label-period">
                        <input type="date" id="rb-since" class="rb-date-input" aria-label="消えた日の開始" value="<?php echo $since ?>">
                        <span class="rb-dates-sep" aria-hidden="true">〜</span>
                        <input type="date" id="rb-until" class="rb-date-input" aria-label="消えた日の終了" value="<?php echo $until ?>">
                    </div>
                    <p class="rb-field-hint">ランキングから消えた日で絞り込み（空欄＝全期間）</p>
                </div>
            </div>
            </div>
        </details>

        <!-- ライブサマリーバー: 選択中の条件を1本の日本語文＋件数で表示 -->
        <div class="rb-summary" aria-label="現在の絞り込み条件">
            <span id="rb-summary-text"><?php echo $titleValue ?></span>
            <span id="rb-summary-count" class="rb-summary-count">— 集計中…</span>
        </div>

        <!-- 詳しい使い方（上級者向け・SEOテキスト） -->
        <aside class="list-aside ranking-desc" style="margin: 1rem 0;">
            <details class="icon-desc">
                <summary style="font-size: 14px;">詳しい使い方と分析の考え方</summary>
                <ul style="padding-left: 1.25rem;">
                    <li>
                        <p class="recommend-desc">
                            「ルーム内容の変更:あり・なし」は、ランキング未掲載になった理由が、ルーム管理者によるルーム内容の変更によるものか、それ以外の理由かを選択し、別けて表示します。
                        </p>
                    </li>
                    <li>
                        <p class="recommend-desc">
                            「掲載状況:復活した（再掲載済み）」を選択している場合、ランキング掲載中のルームが未掲載に変わり、再び掲載中になったルームの一覧を表示します。<br>
                        </p>
                    </li>
                    <li>
                        <p class="recommend-desc">
                            再掲載済み一覧の場合、「〇〇時間で復活」には、未掲載の状態から、どのぐらいで再び掲載されたかが表示されています。
                        </p>
                    </li>
                    <li>
                        <p class="recommend-desc">
                            「掲載状況:復活した」と「ルーム内容の変更:あり」を選択している場合、ルーム管理者がルームの設定を変更して、一旦ランキング・検索に載らなくなり、その後また載るようになったルームの一覧が表示されます。<br>
                        </p>
                    </li>
                    <li>
                        <p class="recommend-desc">
                            「最後に載っていた順位」はランキング順位の上位何％までを表示するかが選べます。<br>
                            この分析機能では、単純なランク圏外ではなく、ルーム内容変更による未掲載や、それ以外の理由で未掲載となったルームに焦点を当てることができます。<br>
                            表示対象を上位に絞ることで、例外的な未掲載にはどのような特徴があるのかが分かりやすくなります。
                        </p>
                    </li>
                    <li>
                        <p class="recommend-desc">
                            「掲載状況:消えたまま（現在未掲載）」を選択している場合、ランキング掲載中のルームが未掲載に変わり、現在も未掲載の状態が続いているルームの一覧を表示します。<br>
                        </p>
                    </li>
                    <li>
                        <p class="recommend-desc">
                            未掲載一覧の場合、「未掲載 〇〇前」には、載らなくなってから、今までどのぐらい経過したかが表示されています。
                        </p>
                    </li>
                    <li>
                        <p class="recommend-desc">
                            「掲載状況:消えたまま」・「ルーム内容の変更:なし」・「最後に載っていた順位:上位50%だけ」を選択している場合、ルームの内容変更・活動量以外の理由で未掲載となった可能性が高いルームの一覧になります。<br>
                        </p>
                    </li>
                    <li>
                        <p class="recommend-desc">
                            メンバー数の表示は、ランキング未掲載になった時点のメンバー数です。<br>メンバー数の隣にあるカッコに括られた数字は、ランキング未掲載になった時点のメンバー数と、いま現在のメンバー数の差です。<br>順位の％は、そのルームの平均的な順位（同一カテゴリー内でのランキング順位）です。
                        </p>
                    </li>
                </ul>
                <p class="recommend-desc" style="font-weight: bold;">オプチャ公式ランキングの掲載を分析する考え方</p>
                <p class="recommend-desc">
                    通常、公式の検索機能で検索できないルーム(検索落ち)は、ランキングにも未掲載と考えられます。
                </p>
                <p class="recommend-desc">
                    例外として、年齢認証されていない端末による検索・WEB版による検索は、強いフィルターがかかるため、これが当てはまらない場合があります。
                    <br>また、年齢認証済であっても、特別に検索ができないキーワードなどがあります。
                </p>
                <p class="recommend-desc">
                    また、端末により検索結果が変わるという公式発表がありますが、それによって検索画面に非表示になるのは、例外と考えることができます。
                </p>
                <p class="recommend-desc">
                    これらを踏まえて、ランキング掲載と検索機能はそれぞれ別々のロジックですが、可視性については一部共通する部分があると考えることができます。
                </p>
                <p class="recommend-desc">
                    例外を除くと、例えば、普段はランキングに掲載されていて、検索が可能なルームの場合、そのルームの設定を変更した後に再びランキングに掲載された時が、同時に検索も可能になる時と考えることができます。
                </p>
                <p class="recommend-desc">
                    なお、ランキングの掲載が途切れる原因の半数は、単純に活動量が低くランク圏外になるためです。また、例外的にランキングから除外されていると考えられるルームでも、検索は可能なパターンがあります。
                </p>
            </details>
        </aside>

        <!-- 完了通知（スクリーンリーダー向け） -->
        <div id="rb-live" class="visually-hidden" aria-live="polite"></div>

        <!-- ロード中表示（初回・2回目以降で共通。常に結果の先頭に出す） -->
        <div id="rb-loading" hidden>
            <div class="rb-progress" aria-hidden="true">
                <div class="rb-progress-bar"></div>
            </div>
            <p class="rb-loading-text">データを取得中…<span id="rb-loading-first">（初回は10秒ほどかかることがあります）</span></p>
        </div>

        <!-- 結果コンテナ（JSがフラグメントを差し込む） -->
        <div id="analytics-results" aria-busy="true">
            <div class="rb-skeleton" aria-hidden="true">
                <?php for ($i = 0; $i < 6; $i++) : ?>
                    <div class="rb-skel-card">
                        <div class="rb-skel-img"></div>
                        <div class="rb-skel-lines">
                            <div class="rb-skel-line"></div>
                            <div class="rb-skel-line short"></div>
                            <div class="rb-skel-line"></div>
                        </div>
                    </div>
                <?php endfor ?>
            </div>
            <noscript>
                <p style="text-align: center;">一覧の表示にはJavaScriptが必要です。</p>
            </noscript>
        </div>
    </main>
    <?php viewComponent('footer_inner') ?>
    <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
    <script>
        (() => {
            'use strict';

            const PAGE_URL = '<?php echo url('labs/publication-analytics') ?>';
            const FRAGMENT_URL = '<?php echo url('labs/publication-analytics/list') ?>';

            const results = document.getElementById('analytics-results');
            const loading = document.getElementById('rb-loading');
            const loadingFirst = document.getElementById('rb-loading-first');
            const live = document.getElementById('rb-live');
            const summaryText = document.getElementById('rb-summary-text');
            const summaryCount = document.getElementById('rb-summary-count');
            const keywordInput = document.getElementById('rb-keyword');
            const clearBtn = document.getElementById('rb-keyword-clear');
            const sinceInput = document.getElementById('rb-since');
            const untilInput = document.getElementById('rb-until');

            // publish-change の9通りの組み合わせを1本の日本語文に合成するテンプレート
            const SUMMARY = {
                '1-0': '内容を変更した直後にランキングから消えて、今も未掲載の部屋',
                '1-1': '内容を変更していないのにランキングから消えて、今も未掲載の部屋',
                '1-2': 'ランキングから消えて、今も未掲載の部屋（変更の有無は問わない）',
                '0-0': '内容変更でいったん消えて、そのあと復活した部屋',
                '0-1': '変更していないのに消えて、そのあと復活した部屋',
                '0-2': 'いったん消えて、そのあと復活した部屋（変更の有無は問わない）',
                '2-0': '内容変更の直後に消えた記録すべて（未掲載・復活どちらも）',
                '2-1': '変更なしで消えた記録すべて（未掲載・復活どちらも）',
                '2-2': '掲載が途切れた記録すべて'
            };

            const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

            const parseState = (search) => {
                const q = new URLSearchParams(search);
                const num = (k, def) => {
                    const v = parseInt(q.get(k), 10);
                    return isNaN(v) ? def : v;
                };
                const date = (k) => {
                    const v = q.get(k) || '';
                    return DATE_RE.test(v) ? v : '';
                };
                return {
                    publish: num('publish', 1),
                    change: num('change', 1),
                    percent: num('percent', 50),
                    page: num('page', 1),
                    keyword: q.get('keyword') || '',
                    since: date('since'),
                    until: date('until')
                };
            };

            let state = parseState(location.search);

            // クエリ順は pagerUrl() (PHP) と同一: change → publish → percent → keyword → since → until (→ page>1)
            // CDNキャッシュキーの分裂を防ぐため固定
            const buildQuery = (s) => {
                const q = new URLSearchParams();
                q.set('change', s.change);
                q.set('publish', s.publish);
                q.set('percent', s.percent);
                q.set('keyword', s.keyword);
                q.set('since', s.since);
                q.set('until', s.until);
                if (s.page > 1) q.set('page', s.page);
                return q.toString();
            };

            const pageUrl = (s) => PAGE_URL + '?' + buildQuery(s);
            const fragmentUrl = (s) => FRAGMENT_URL + '?' + buildQuery(s);

            const updateSummary = () => {
                let text = SUMMARY[state.publish + '-' + state.change] || '';
                if (state.percent < 100) text += '・最終順位 上位' + state.percent + '%以内';
                if (state.since !== '' || state.until !== '') {
                    text += '・' + state.since.replaceAll('-', '/') + '〜' + state.until.replaceAll('-', '/') + 'に消えた';
                }
                if (state.keyword !== '') text += '・「' + state.keyword + '」を含む';
                summaryText.textContent = text;
            };

            const toggleClear = () => {
                clearBtn.classList.toggle('is-hidden', keywordInput.value === '');
            };

            const syncControls = () => {
                document.querySelectorAll('.rb-seg input[type="radio"]').forEach((r) => {
                    const checked = String(state[r.name]) === r.value;
                    r.checked = checked;
                    r.closest('.rb-seg-item').classList.toggle('is-selected', checked);
                });
                if (keywordInput.value !== state.keyword) keywordInput.value = state.keyword;
                if (sinceInput.value !== state.since) sinceInput.value = state.since;
                if (untilInput.value !== state.until) untilInput.value = state.until;
                toggleClear();
                let anyPreset = false;
                document.querySelectorAll('.rb-preset').forEach((b) => {
                    const active =
                        state.since === '' && state.until === '' &&
                        Number(b.dataset.publish) === state.publish &&
                        Number(b.dataset.change) === state.change &&
                        Number(b.dataset.percent) === state.percent;
                    b.classList.toggle('is-active', active);
                    anyPreset = anyPreset || active;
                });
                // プリセットに無い組み合わせが効いているときは詳細設定を開いて隠さない（自動では閉じない）
                if (!anyPreset) document.getElementById('rb-advanced').open = true;
            };

            let aborter = null;
            let initial = true;

            const setBusy = (busy) => {
                results.setAttribute('aria-busy', busy ? 'true' : 'false');
                loading.hidden = !busy; // 初回・2回目以降とも、常に結果の先頭にプログレスバー＋取得中テキスト
                if (busy) {
                    loadingFirst.hidden = !initial; // 「（初回は10秒ほど…）」は初回だけ
                    if (!initial) results.classList.add('is-stale');
                } else {
                    results.classList.remove('is-stale');
                }
            };

            const load = (opts) => {
                opts = opts || {};
                if (aborter) aborter.abort();
                aborter = new AbortController();
                updateSummary();
                summaryCount.textContent = '— 集計中…';
                setBusy(true);
                const s = Object.assign({}, state);
                fetch(fragmentUrl(s), { signal: aborter.signal })
                    .then((res) => {
                        if (res.ok) return res.text();
                        if (res.status === 404) {
                            if (s.page > 1) {
                                // ページ範囲外: 1ページ目に戻して再取得
                                state.page = 1;
                                load({ push: opts.push });
                                return null;
                            }
                            // page=1での404は通常起きない。通常遷移にフォールバック
                            location.href = pageUrl(s);
                            return null;
                        }
                        // その他のエラーはエラー表示＋再試行（遷移ループ防止）
                        throw new Error('HTTP ' + res.status);
                    })
                    .then((html) => {
                        if (html === null || html === undefined) return;
                        results.innerHTML = html;
                        initial = false;
                        setBusy(false);
                        const inner = results.querySelector('.rb-results-inner');
                        const total = inner ? Number(inner.dataset.totalRecords) || 0 : 0;
                        const page = inner ? Number(inner.dataset.page) || 1 : 1;
                        const dataTitle = inner ? inner.dataset.title : '';
                        summaryCount.textContent = '— ' + total.toLocaleString('ja-JP') + '件';
                        document.title = 'オプチャ公式ランキング掲載の分析 ' + (page > 1 ? '(' + page + 'ページ目) ' : '') + dataTitle;
                        live.textContent = total.toLocaleString('ja-JP') + '件の結果を表示しました';
                        if (opts.push) history.pushState(state, '', pageUrl(state));
                        if (opts.scroll) results.scrollIntoView({ block: 'start' });
                    })
                    .catch((err) => {
                        if (err && err.name === 'AbortError') return;
                        initial = false;
                        setBusy(false);
                        summaryCount.textContent = '';
                        results.innerHTML = '<div class="rb-error"><p>読み込みに失敗しました</p><button type="button" class="rb-retry">再試行</button></div>';
                    });
            };

            // セグメント（radio）
            document.querySelectorAll('.rb-seg input[type="radio"]').forEach((r) => {
                r.addEventListener('change', () => {
                    if (!r.checked) return;
                    state[r.name] = Number(r.value);
                    state.page = 1;
                    syncControls();
                    load({ push: true });
                });
            });

            // プリセットチップ（プリセット＝完成された見え方なので、期間指定もリセットする）
            document.querySelectorAll('.rb-preset').forEach((b) => {
                b.addEventListener('click', () => {
                    state.publish = Number(b.dataset.publish);
                    state.change = Number(b.dataset.change);
                    state.percent = Number(b.dataset.percent);
                    state.since = '';
                    state.until = '';
                    state.page = 1;
                    syncControls();
                    load({ push: true });
                });
            });

            // 期間（消えた時期）
            [sinceInput, untilInput].forEach((input) => {
                input.addEventListener('change', () => {
                    const v = DATE_RE.test(input.value) ? input.value : '';
                    const key = input === sinceInput ? 'since' : 'until';
                    if (state[key] === v) return;
                    state[key] = v;
                    state.page = 1;
                    syncControls();
                    load({ push: true });
                });
            });

            // キーワード（Enter確定のみ・IME変換確定は無視。iOS IMEゾンビ対策で変換中は×を隠す）
            let composing = false;
            keywordInput.addEventListener('compositionstart', () => {
                composing = true;
                clearBtn.classList.add('is-hidden');
            });
            keywordInput.addEventListener('compositionend', () => {
                composing = false;
                toggleClear();
            });
            keywordInput.addEventListener('input', () => {
                if (!composing) toggleClear();
            });
            keywordInput.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') return;
                if (e.isComposing || composing) return;
                e.preventDefault();
                if (keywordInput.value === state.keyword) return;
                state.keyword = keywordInput.value;
                state.page = 1;
                load({ push: true });
            });
            clearBtn.addEventListener('click', () => {
                keywordInput.value = '';
                toggleClear();
                if (state.keyword === '') return;
                state.keyword = '';
                state.page = 1;
                load({ push: true });
            });

            // 結果コンテナへのイベント委譲（innerHTML差し替えでリスナーが消えるため）
            const parsePageFromUrl = (u) => {
                try {
                    const url = new URL(u, location.origin);
                    const v = parseInt(url.searchParams.get('page') || '1', 10);
                    return isNaN(v) ? 1 : v;
                } catch (_) {
                    return null;
                }
            };
            results.addEventListener('change', (e) => {
                const sel = e.target.closest('#page-selector');
                if (!sel || !sel.value) return;
                const p = parsePageFromUrl(sel.value);
                if (p === null) return;
                state.page = p;
                load({ push: true, scroll: true });
            });
            results.addEventListener('click', (e) => {
                if (e.target.closest('.rb-retry')) {
                    load({ push: false });
                    return;
                }
                const a = e.target.closest('.search-pager a');
                if (!a) return;
                e.preventDefault();
                const p = parsePageFromUrl(a.href);
                if (p === null) return;
                state.page = p;
                load({ push: true, scroll: true });
            });

            // ブラウザバック/フォワード
            window.addEventListener('popstate', () => {
                state = parseState(location.search);
                syncControls();
                load({ push: false });
            });


            // 初回ロード
            syncControls();
            load({ push: false });
        })();
    </script>
</body>

</html>
