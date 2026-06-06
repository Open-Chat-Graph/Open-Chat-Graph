/* ============================================================
   テーマ切替（ダークモード）
   - 解決順: localStorage('theme') > OS設定(prefers-color-scheme)
   - <head> 内で同期実行され、描画前に data-theme を確定する（FOUC防止）。
     そのため defer/async を付けずに読み込むこと。
   - 公開API: window.ocTheme
       .resolved()      … 'dark' | 'light'（現在の見た目）
       .stored()        … 'dark' | 'light' | null（明示選択。null=OS連動）
       .set(t)          … 'dark' | 'light' | null を保存して即適用
       .toggle()        … 見た目を反転して保存
     変更時は document に CustomEvent('octhemechange', {detail:{theme}}) を発火
     （React/Chart.js 側はこれを購読して再描画する）。
   - meta[name=theme-color] も解決テーマに合わせて更新する。
   ============================================================ */
(function () {
  'use strict';

  var KEY = 'theme';
  var META_LIGHT = '#ffffff';
  var META_DARK = '#0f172a'; /* tokens.css の --c-bg (slate-900) と同期 */

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
    var meta = document.querySelector('meta[name="theme-color"]');
    if (!meta) {
      meta = document.createElement('meta');
      meta.setAttribute('name', 'theme-color');
      (document.head || document.documentElement).appendChild(meta);
    }
    meta.setAttribute('content', theme === 'dark' ? META_DARK : META_LIGHT);
  }

  function apply(fire) {
    var t = stored();
    var root = document.documentElement;
    if (t) {
      root.setAttribute('data-theme', t);
    } else {
      root.removeAttribute('data-theme'); /* OS連動（CSSの media query に委ねる） */
    }
    var r = resolved();
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
    toggle: function () {
      return set(resolved() === 'dark' ? 'light' : 'dark');
    },
  };

  /* トグルボタン（.theme-toggle-btn）はイベントデリゲーションで拾う。
     PHPヘッダ/Reactヘッダのどちらでも、ボタンを置くだけで動く */
  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest && e.target.closest('.theme-toggle-btn');
    if (btn) window.ocTheme.toggle();
  });

  /* 初期適用（head 内同期実行 = 最初の描画前） */
  apply(false);
})();
