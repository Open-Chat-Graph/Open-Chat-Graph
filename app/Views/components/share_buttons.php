<?php

/**
 * 共有ボタン列（OS ネイティブ共有・LINE・X・リンクコピー）＋コピー完了トースト。
 * oc ページの共有導線を共通コンポーネント化したもの（oc・/recommend・トップで共用）。
 * スタイル・挙動は自己完結（インライン style/script）。1ページ1回だけ設置する想定（id を使うため）。
 *
 * @var string      $_shareUrl   共有する URL（必須。`_`プレフィックスで自動エスケープを通さず、ここで明示エスケープする）
 * @var array|null  $_shareGa    GA4 計測(dataLayer)の share イベントに足す追加パラメータ（例 ['oc_id' => 1]）。省略可
 * @var string|null $_shareStyle ルート要素の style 上書き（例 'margin-top: 0'。前後ブロックと余白が二重になる場合の調整用）。省略可
 *
 * 共有本文は「ページタイトル(サイト名入り)＋改行＋URL」で統一（JS が document.title から組み立てる）。
 * 共有リンクの og:image は各ページの動的カードで展開される。
 */

$_shareGa = $_shareGa ?? [];
?>
<div class="share-btns"<?php if (!empty($_shareStyle)) : ?> style="<?php echo h($_shareStyle) ?>"<?php endif ?>>
  <span class="share-btns__label"><?php echo t('共有') ?></span>
  <button type="button" class="share-btns__btn share-btns__btn--sub" id="share-btns-native" hidden aria-label="<?php echo t('共有') ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 16.1c-.8 0-1.5.3-2 .8l-7.1-4.2c0-.2.1-.5.1-.7s0-.5-.1-.7L16 7.2c.5.5 1.2.8 2 .8a3 3 0 1 0-3-3c0 .2 0 .5.1.7L8 9.8a3 3 0 1 0 0 4.4l7.1 4.2c0 .2-.1.4-.1.6a3 3 0 1 0 3-2.9z"/></svg>
  </button>
  <a class="share-btns__btn share-btns__btn--line" id="share-btns-line" href="https://social-plugins.line.me/lineit/share?url=<?php echo urlencode($_shareUrl) ?>" target="_blank" rel="noopener" aria-label="LINE">
    <img src="<?php echo fileUrl('assets/line.svg', urlRoot: '') ?>" alt="" width="44" height="44">
  </a>
  <a class="share-btns__btn share-btns__btn--x" id="share-btns-x" href="https://x.com/intent/post?text=<?php echo urlencode($_shareUrl . "\n") ?>" target="_blank" rel="noopener" aria-label="X">
    <svg viewBox="0 0 240 240" aria-hidden="true"><path d="M88.2 60.66L169.46 178.81H151.42L70.16 60.66H88.2ZM92.93 51.66H53.04L146.68 187.81H186.57L92.93 51.66Z"/><path d="M132.54 109.25L182.24 51.66H170.99L127.55 101.99L132.54 109.25Z"/><path d="M105.36 127.72L53.04 188.34H64.3L110.35 134.98L105.36 127.72Z"/></svg>
  </a>
  <button type="button" class="share-btns__btn share-btns__btn--sub" id="share-btns-copy" aria-label="<?php echo t('リンクをコピー') ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 1H4a2 2 0 0 0-2 2v14h2V3h12V1zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H8V7h11v14z"/></svg>
  </button>
</div>
<div class="share-btns-toast" id="share-btns-toast" role="status" aria-live="polite"><?php echo t('コピーしました') ?></div>
<style>
  .share-btns { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; margin: var(--sp-section-gap) 1rem 0; }
  .share-btns__label { font-size: 13px; font-weight: 700; letter-spacing: .04em; color: var(--c-text-4, #8b95a7); }
  .share-btns__btn { width: 44px; height: 44px; flex: 0 0 44px; border-radius: 50%; overflow: hidden; display: inline-flex; align-items: center; justify-content: center; padding: 0; border: none; background: none; cursor: pointer; text-decoration: none; transition: transform .12s ease, filter .12s ease; }
  .share-btns__btn:hover { transform: translateY(-2px); filter: brightness(1.08); }
  .share-btns__btn:active { transform: translateY(0); }
  .share-btns__btn:focus-visible { outline: 2px solid var(--c-grad-blue-btn, #5a8cff); outline-offset: 2px; }
  .share-btns__btn svg { width: 22px; height: 22px; display: block; }
  .share-btns__btn--line { background: #06C755; }
  /* LINEアイコンは周囲に緑の余白を残す（円いっぱいに敷くと切り抜き感が強いため縮小して中央寄せ） */
  .share-btns__btn--line img { width: 68%; height: 68%; display: block; }
  /* X は真っ黒なのでダーク背景で溶ける。薄い枠で輪郭を出す */
  .share-btns__btn--x { background: #000; border: 1px solid rgba(255, 255, 255, .22); }
  .share-btns__btn--x svg { width: 20px; height: 20px; fill: #fff; }
  .share-btns__btn--sub { background: var(--c-surface-2, rgba(127, 127, 127, .14)); border: 1px solid var(--c-border, rgba(127, 127, 127, .24)); }
  .share-btns__btn--sub svg { fill: var(--c-text-3, #c2cad6); }
  .share-btns__btn[hidden] { display: none; }
  /* コピー完了の軽いトースト（すぐ消える） */
  .share-btns-toast { position: fixed; left: 50%; bottom: 28px; transform: translateX(-50%) translateY(10px); z-index: 9999; padding: 10px 18px; border-radius: 10px; background: rgba(22, 26, 42, .96); color: #fff; font-size: 14px; font-weight: 700; box-shadow: 0 8px 28px rgba(0, 0, 0, .38); opacity: 0; pointer-events: none; transition: opacity .18s ease, transform .18s ease; }
  .share-btns-toast.is-visible { opacity: 1; transform: translateX(-50%) translateY(0); }
  @media (prefers-reduced-motion: reduce) { .share-btns-toast { transition: opacity .18s ease; transform: translateX(-50%); } }
</style>
<script>
  (function () {
    var url = <?php echo json_encode($_shareUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    // ページ種別ごとの追加パラメータ（oc_id 等）を含めて GA4(GTM dataLayer経由)で計測。method で LINE/X/コピー/OS共有 を区別
    var extras = <?php echo json_encode((object)$_shareGa, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    var d = (window.dataLayer = window.dataLayer || []);
    function track(method) {
      try {
        var ev = { event: 'share', method: method };
        for (var k in extras) ev[k] = extras[k];
        d.push(ev);
      } catch (e) {}
    }
    // 共有本文は「ページタイトル(サイト名入り)＋改行＋URL」で統一
    function shareText() { return document.title + '\n' + url; }

    var xBtn = document.getElementById('share-btns-x');
    if (xBtn) {
      // X はページタイトルを使い、URLの後にも改行を入れる（本文だけで組み立てる）
      xBtn.href = 'https://x.com/intent/post?text=' + encodeURIComponent(shareText() + '\n');
      xBtn.addEventListener('click', function () { track('x'); });
    }
    var lineBtn = document.getElementById('share-btns-line');
    if (lineBtn) { lineBtn.addEventListener('click', function () { track('line'); }); }

    var nativeBtn = document.getElementById('share-btns-native');
    if (navigator.share) {
      nativeBtn.hidden = false;
      nativeBtn.addEventListener('click', function () {
        track('native');
        navigator.share({ title: document.title, url: url }).catch(function () {});
      });
    }

    var toast = document.getElementById('share-btns-toast');
    var toastTimer;
    function showToast() {
      if (!toast) return;
      toast.classList.add('is-visible');
      clearTimeout(toastTimer);
      toastTimer = setTimeout(function () { toast.classList.remove('is-visible'); }, 1600);
    }
    var copyBtn = document.getElementById('share-btns-copy');
    copyBtn.addEventListener('click', function () {
      if (!navigator.clipboard) return;
      navigator.clipboard.writeText(shareText()).then(function () {
        track('copy');
        showToast();
      }).catch(function () {});
    });
  })();
</script>
