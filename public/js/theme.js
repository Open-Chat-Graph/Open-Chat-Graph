/* ============================================================
   テーマ切替（ダークモード）
   - 解決順: localStorage('theme') > OS設定(prefers-color-scheme)
   - <head> 内で同期実行され、描画前に data-theme を確定する（FOUC防止）。
     そのため defer/async を付けずに読み込むこと。
   - 公開API: window.ocTheme
       .resolved()      … 'dark' | 'light'（現在の見た目）
       .stored()        … 'dark' | 'light' | null（明示選択。null=OS連動）
       .set(t)          … 'dark' | 'light' | null(=自動) を保存して即適用
       .cycle()         … ライト → ダーク → 自動 → … の3状態切替
     変更時は document に CustomEvent('octhemechange', {detail:{theme}}) を発火
     （React/Chart.js 側はこれを購読して再描画する）。
   - meta[name=theme-color] も解決テーマに合わせて更新する。
   ============================================================ */
(function () {
  'use strict';

  var KEY = 'theme';
  var IS_IOS = /iP(hone|ad|od)/.test(navigator.userAgent);
  /* iPhone 限定のヘッダー演出（吹っ飛ばし+フェード）等の CSS 分岐用フック */
  if (IS_IOS) document.documentElement.classList.add('is-ios');
  var META_LIGHT = '#ffffff';
  var META_DARK = '#0f172a'; /* tokens.css の --c-bg と同期 */

  function stored() {
    try {
      var t = localStorage.getItem(KEY);
      return t === 'dark' || t === 'light' ? t : null;
    } catch (e) {
      return null;
    }
  }

  function osPrefersDark() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  }

  function resolved() {
    var t = stored();
    if (t) return t;
    return osPrefersDark() ? 'dark' : 'light';
  }

  function applyMeta(theme) {
    var metas = document.querySelectorAll('meta[name="theme-color"]');
    if (IS_IOS) {
      /* iOS Safari: theme-color を指定するとバーが「ベタ塗り」になり
         ネイティブの半透明マテリアル（コンテンツがバーに透ける挙動）が失われる。
         メタを外せば Safari がページ背景から自動着色し、半透明が効く。
         ダーク/ライトの色追従はページ背景（html の --c-bg）経由で維持される */
      metas.forEach(function (m) { m.remove(); });
      return;
    }
    var meta = metas[0];
    if (!meta) {
      meta = document.createElement('meta');
      meta.setAttribute('name', 'theme-color');
      (document.head || document.documentElement).appendChild(meta);
    }
    meta.setAttribute('content', theme === 'dark' ? META_DARK : META_LIGHT);
  }

  function apply(fire) {
    var root = document.documentElement;
    var r = resolved();
    /* 明示選択が無い場合も「解決済みテーマ」を常に属性へ反映する。
       これにより Tailwind の darkMode: selector 等、属性ベースの仕組みと
       完全に同期する（tokens.css の prefers-color-scheme ブロックは
       JS が動かない初回描画のためのフォールバックとして残している）。 */
    root.setAttribute('data-theme', r);
    /* UI用: ユーザーの選択そのもの（auto を区別する。アイコン出し分けに使用） */
    root.setAttribute('data-theme-pref', stored() || 'auto');
    applyMeta(r);
    if (fire) {
      try {
        document.dispatchEvent(new CustomEvent('octhemechange', { detail: { theme: r } }));
      } catch (e) { /* 古いブラウザは無視 */ }
    }
    return r;
  }

  /* テーマ切替の瞬間だけ全 transition を殺す。
     理由(実測): transition を持つ要素（MUI Chip の background-color 0.3s 等）は、
     ルート属性切替による var() 端点の変化と進行中 transition が競合すると
     Chromium が旧テーマの値で固まることがある。切替を「遷移なしの一発置換」に
     することでこれを根絶し、ライト/ダークの混在フェードも防ぐ。 */
  function withoutTransitions(fn) {
    var style = document.createElement('style');
    style.textContent = '*,*::before,*::after{transition:none!important}';
    (document.head || document.documentElement).appendChild(style);
    var result = fn();
    void document.documentElement.offsetWidth; /* 強制リフロー（無遷移状態で再計算させる） */
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        style.remove();
      });
    });
    return result;
  }

  function set(t) {
    try {
      if (t === 'dark' || t === 'light') localStorage.setItem(KEY, t);
      else localStorage.removeItem(KEY);
    } catch (e) { /* private mode 等は無視 */ }
    return withoutTransitions(function () {
      return apply(true);
    });
  }

  /* OS設定の変化に追従（明示選択が無い場合のみ見た目が変わる） */
  if (window.matchMedia) {
    try {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
        if (!stored()) apply(true);
      });
    } catch (e) { /* Safari<14 の addListener 差異は無視（次回訪問時に反映） */ }
  }

  window.ocTheme = {
    resolved: resolved,
    stored: stored,
    set: set,
    /* ライト → ダーク → 自動(OS連動) の3状態を巡回 */
    cycle: function () {
      var cur = stored() || 'auto';
      var next = cur === 'light' ? 'dark' : cur === 'dark' ? null : 'light';
      return set(next);
    },
  };

  /* トグルボタン（.theme-toggle-btn）はイベントデリゲーションで拾う。
     PHPヘッダ/Reactヘッダのどちらでも、ボタンを置くだけで動く */
  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest && e.target.closest('.theme-toggle-btn');
    if (btn) window.ocTheme.cycle();
  });

  /* 初期適用（head 内同期実行 = 最初の描画前） */
  apply(false);
})();
