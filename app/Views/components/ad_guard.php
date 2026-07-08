<?php

/**
 * 広告ブロック検出＋案内オーバーレイ（HTMLにインライン出力・毎リクエストでローテーション）
 *
 * 経緯: 旧 public/js/security.js は固定ファイル名＋固定シンボル名(detectAdBlock 等)だったため、
 * uAssets(uBlock Origin/uBO Lite)に openchat-review.me 名指しで
 *   openchat-review.me##+js(aost, document.querySelectorAll, security.js)
 *   openchat-review.me##+js(nosiif, detectAdBlock)
 * のように「ファイル名」「関数名」「querySelectorAll」を手がかり(needle)に潰された。
 *
 * 対策（構造的）:
 *  1) 外部ファイルをやめ、このコンポーネントから <script> をHTMLにインライン出力する
 *     → 狙える固定ファイル名が消える（URLブロックも効かない）。
 *  2) 全ての関数/変数名を毎リクエスト乱数化（スタックトレースに固定名が出ない）。
 *  3) 機微な文字列（'adsbygoogle' / 'data-ad-status' / 案内文 / URL / localStorageキー 等）を
 *     毎リクエストのXOR鍵でエンコードして埋め込み、実行時に復号する
 *     → 配信HTMLに固定の平文が出ないので、文字列needle も成立しない。
 *  4) 枠の取得は document.querySelectorAll を使わず getElementsByClassName / getElementsByTagName
 *     にする → 現行の aost(querySelectorAll罠) を回避。
 *
 * サイト本体(React)も同じ DOM API・同じ 'adsbygoogle' を使うため、相手は needle 無しに
 * このスクリプトだけを選択的に潰せない（潰すと正規の広告表示やサイト自体に巻き添えが出る）。
 *
 * 検出ロジック自体は旧 security.js と等価:
 *  (A) iframe が computed 1px に潰されている / (B) inline style に width:1px!important+height:1px!important /
 *  (C) data-ad-status="filled" を名乗るのに iframe が src も inline style も持たない(uBOL新サロゲート)。
 *  no fill(unfilled)・iframe無し枠は除外し、誤検知ゼロを維持。
 */

// 運用方針転換により一旦無効化（GoogleAdsenseConfig::$enableAdBlockGuard）。
// 各 View の viewComponent('ad_guard') 呼び出しは残すが、フラグが false の間は何も出力しない。
// 復活させたいときは同フラグを true に戻すだけでよい。
if (!\App\Config\GoogleAdsenseConfig::$enableAdBlockGuard) {
    return;
}

// --- 乱数識別子（毎リクエスト） ---
$rid = static function (): string {
    return 'o' . substr(bin2hex(random_bytes(8)), 0, 10);
};

$names = [
    'dec', 'key', 'host', 'bkey', 'revt', 'cls', 'aAbg', 'aAds', 'vDone', 'vFilled', 'vUnfilled',
    'scriptId', 'adsUrl', 'crawlerUrl', 'msg',
    'findSlots', 'allIns', 'adsRender', 'detect', 'mark', 'pushDL', 'recover', 'whiteOut',
    'run', 'showGear',
    'fired', 'observers', 'poll', 'stop', 'cleanup', 'tryD', 'attach', 'ro', 'watched',
    'ovId', 'cOverlay', 'cCard', 'cIcon', 'cTitle', 'cBody', 'cNote', 'cBtn',
];
$N = [];
foreach ($names as $k) {
    $N[$k] = $rid();
}

// --- 文字列エンコード（毎リクエストのXOR鍵。配信HTMLに平文を出さない） ---
$keyBytes = array_values(unpack('C*', random_bytes(32)));
$keyLen = count($keyBytes);
$enc = static function (string $s) use ($N, $keyBytes, $keyLen): string {
    $bytes = array_values(unpack('C*', $s)); // UTF-8 バイト列（日本語等もOK）
    $out = [];
    foreach ($bytes as $i => $b) {
        $out[] = $b ^ $keyBytes[$i % $keyLen];
    }
    // 実行時に dec([...]) で復号する式を返す
    return $N['dec'] . '([' . implode(',', $out) . '])';
};
$keyJs = '[' . implode(',', $keyBytes) . ']';

// 案内文（表示言語ごと。<html lang> で出し分け）
$messages = [
    'ja' => [
        'title' => '広告が表示されていません',
        'body' => 'オプチャグラフは広告収入で運営されています。広告ブロッカーをご利用の場合は、解除するか当サイトを許可リストに追加してください。',
        'note' => '広告ブロッカーを使用していないのにこの画面が表示される場合は、お手数ですがページの再読み込みをお試しください。',
        'button' => '再読み込み',
    ],
    'zh' => [
        'title' => '廣告未能顯示',
        'body' => '本網站以廣告收入維持營運。如果您正在使用廣告攔截器，請將本站加入白名單或暫時停用。',
        'note' => '若您並未使用廣告攔截器卻看到此畫面，請嘗試重新整理頁面。',
        'button' => '重新整理',
    ],
    'th' => [
        'title' => 'ไม่สามารถแสดงโฆษณาได้',
        'body' => 'เว็บไซต์นี้ดำเนินการด้วยรายได้จากโฆษณา หากคุณกำลังใช้ตัวบล็อกโฆษณา โปรดปิดการใช้งานหรือเพิ่มเว็บไซต์นี้ในรายการที่อนุญาต',
        'note' => 'หากคุณไม่ได้ใช้ตัวบล็อกโฆษณาแต่ยังเห็นหน้านี้ โปรดลองโหลดหน้าเว็บใหม่อีกครั้ง',
        'button' => 'โหลดหน้าใหม่',
    ],
    'en' => [
        'title' => 'Ads aren’t loading',
        'body' => 'OpenChat Graph is supported by ad revenue. If you’re using an ad blocker, please disable it or add this site to your allowlist.',
        'note' => 'If you’re not using an ad blocker and still see this screen, please try reloading the page.',
        'button' => 'Reload',
    ],
];

// 案内文の各文字の間に毎リクエストでゼロ幅文字をランダム挿入する（表示は完全に不変）。
// uBlock のコスメティック :has-text()/:matches-text() は要素の文字列を見て隠すため、
// 文面を「固定の連続文字列」でなくすことで、案内文そのものを手がかりにした非表示化も成立させない。
$zwInject = static function (string $s): string {
    $zw = ["\u{200B}", "\u{200C}", "\u{2060}", "\u{FEFF}"]; // ZWSP / ZWNJ / WORD JOINER / ZWNBSP（全て幅0・不可視）
    $cps = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $last = count($cps) - 1;
    $out = '';
    foreach ($cps as $i => $cp) {
        $out .= $cp;
        if ($i !== $last) {
            $out .= $zw[random_int(0, count($zw) - 1)];
        }
    }
    return $out;
};
$messages = array_map(static fn(array $m): array => array_map($zwInject, $m), $messages);

// 復号式（エンコード済み）
$E = [
    'host' => $enc('openchat-review.me'),
    'bkey' => $enc('ocgab_blocked_at'),
    'revt' => $enc('adblock_recovered'),
    'cls' => $enc('adsbygoogle'),
    'aAbg' => $enc('data-adsbygoogle-status'),
    'aAds' => $enc('data-ad-status'),
    'vDone' => $enc('done'),
    'vFilled' => $enc('filled'),
    'vUnfilled' => $enc('unfilled'),
    'scriptId' => $enc('ads-by-google-script'),
    'adsUrl' => $enc('https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js'),
    'crawlerUrl' => $enc('https://raw.githubusercontent.com/monperrus/crawler-user-agents/master/crawler-user-agents.json'),
    'msg' => $enc(json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
    'admEnable' => $enc('admin-enable='),
    'gearId' => $enc('admin-gear-btn'),
    'checkUrl' => $enc('/admin-check'),
    'ocgHdr' => $enc('X-Ocg-Client'),
];

// 乱数ノンス（同一コードでもバイト列を変える保険）
$nonce = bin2hex(random_bytes(8));

// オーバーレイの z-index を毎リクエストで僅かに変える（ほぼ最大値だが固定値にしない）。
// 2147483647 という典型的なアンチアドブロックの指紋を :matches-css(z-index:...) で狙われないため。
$zindex = 2147483000 + random_int(0, 647);

?>
<script>/* <?php echo $nonce; ?> */(function () {
  try {
    var <?php echo $N['key']; ?> = <?php echo $keyJs; ?>;
    function <?php echo $N['dec']; ?>(a) {
      var b = new Uint8Array(a.length);
      for (var i = 0; i < a.length; i++) { b[i] = a[i] ^ <?php echo $N['key']; ?>[i % <?php echo $N['key']; ?>.length]; }
      return new TextDecoder().decode(b);
    }

    var <?php echo $N['host']; ?> = <?php echo $E['host']; ?>;
    var <?php echo $N['bkey']; ?> = <?php echo $E['bkey']; ?>;
    var <?php echo $N['revt']; ?> = <?php echo $E['revt']; ?>;
    var <?php echo $N['cls']; ?> = <?php echo $E['cls']; ?>;
    var <?php echo $N['aAbg']; ?> = <?php echo $E['aAbg']; ?>;
    var <?php echo $N['aAds']; ?> = <?php echo $E['aAds']; ?>;
    var <?php echo $N['vDone']; ?> = <?php echo $E['vDone']; ?>;
    var <?php echo $N['vFilled']; ?> = <?php echo $E['vFilled']; ?>;
    var <?php echo $N['vUnfilled']; ?> = <?php echo $E['vUnfilled']; ?>;
    var <?php echo $N['scriptId']; ?> = <?php echo $E['scriptId']; ?>;
    var <?php echo $N['msg']; ?> = JSON.parse(<?php echo $E['msg']; ?>);

    function <?php echo $N['showGear']; ?>() {
      var g = document.getElementById(<?php echo $E['gearId']; ?>);
      if (g) { g.style.display = 'flex'; }
    }
    var adm = (document.cookie.split('; ').filter(function (r) { return r.indexOf(<?php echo $E['admEnable']; ?>) === 0; })[0] || '').split('=')[1];
    if (adm) {
      var hh = {}; hh[<?php echo $E['ocgHdr']; ?>] = '1';
      fetch(<?php echo $E['checkUrl']; ?>, { headers: hh, cache: 'no-store' })
        .then(function (r) { if (r.ok) { <?php echo $N['showGear']; ?>(); } else { <?php echo $N['run']; ?>(); } })
        .catch(function () { <?php echo $N['run']; ?>(); });
    } else {
      <?php echo $N['run']; ?>();
    }

    function <?php echo $N['allIns']; ?>() {
      var c = document.getElementsByClassName(<?php echo $N['cls']; ?>), o = [];
      for (var i = 0; i < c.length; i++) { if (c[i].tagName === 'INS') { o.push(c[i]); } }
      return o;
    }
    function <?php echo $N['findSlots']; ?>() {
      var c = <?php echo $N['allIns']; ?>(), o = [];
      for (var i = 0; i < c.length; i++) { if (c[i].getAttribute(<?php echo $N['aAbg']; ?>) === <?php echo $N['vDone']; ?>) { o.push(c[i]); } }
      return o;
    }

    function <?php echo $N['adsRender']; ?>() {
      var s = <?php echo $N['findSlots']; ?>(), n = 0;
      for (var i = 0; i < s.length; i++) {
        var e = s[i];
        if (e.getAttribute(<?php echo $N['aAds']; ?>) === <?php echo $N['vUnfilled']; ?>) { continue; }
        var f = e.getElementsByTagName('iframe')[0];
        if (!f) { continue; }
        var st = window.getComputedStyle(f);
        if (parseFloat(st.width) > 1 && parseFloat(st.height) > 1) { n++; }
      }
      return n > 0;
    }

    function <?php echo $N['detect']; ?>() {
      var s = <?php echo $N['findSlots']; ?>();
      if (!s.length) { return false; }
      var total = 0, blocked = 0;
      for (var i = 0; i < s.length; i++) {
        var e = s[i];
        if (e.getAttribute(<?php echo $N['aAds']; ?>) === <?php echo $N['vUnfilled']; ?>) { continue; }
        total++;
        var f = e.getElementsByTagName('iframe')[0];
        if (!f) { continue; }
        var cs = window.getComputedStyle(f);
        var w = parseFloat(cs.width), h = parseFloat(cs.height);
        var byComputed = w > 0 && w <= 1 && h > 0 && h <= 1;
        var sa = f.getAttribute('style') || '';
        var byInline = /(^|[;\s])width:\s*1px\s*!important/.test(sa) && /(^|[;\s])height:\s*1px\s*!important/.test(sa);
        var fake = e.getAttribute(<?php echo $N['aAds']; ?>) === <?php echo $N['vFilled']; ?> && (f.getAttribute('src') || '') === '' && sa.trim() === '';
        if (byComputed || byInline || fake) { blocked++; }
      }
      return total > 0 && blocked / total >= 0.5;
    }

    function <?php echo $N['mark']; ?>() {
      try { if (!localStorage.getItem(<?php echo $N['bkey']; ?>)) { localStorage.setItem(<?php echo $N['bkey']; ?>, String(Date.now())); } } catch (e) {}
    }
    function <?php echo $N['pushDL']; ?>(name, params) {
      if (location.hostname !== <?php echo $N['host']; ?>) { return; }
      try { window.dataLayer = window.dataLayer || []; window.dataLayer.push(Object.assign({ event: name }, params || {})); } catch (e) {}
    }
    function <?php echo $N['recover']; ?>() {
      var raw;
      try { raw = localStorage.getItem(<?php echo $N['bkey']; ?>); } catch (e) { return; }
      if (!raw) { return; }
      if (!<?php echo $N['adsRender']; ?>() || <?php echo $N['detect']; ?>()) { return; }
      var at = parseInt(raw, 10);
      var ms = Date.now() - at;
      var THIRTY = 30 * 24 * 3600 * 1000;
      if (!(at > 0) || ms > THIRTY) { try { localStorage.removeItem(<?php echo $N['bkey']; ?>); } catch (e) {} return; }
      var bucket = ms < 30 * 60 * 1000 ? 'within_30min' : (ms < 24 * 3600 * 1000 ? 'within_1day' : 'within_30days');
      <?php echo $N['pushDL']; ?>(<?php echo $N['revt']; ?>, { recovery_elapsed: bucket });
      try { localStorage.removeItem(<?php echo $N['bkey']; ?>); } catch (e) {}
    }

    function <?php echo $N['whiteOut']; ?>() {
      if (document.getElementById('<?php echo $N['ovId']; ?>')) { return; }
      <?php echo $N['mark']; ?>();
      var rawLang = (document.documentElement.lang || 'ja').toLowerCase();
      var lang = rawLang.indexOf('zh') === 0 ? 'zh' : (rawLang.indexOf('th') === 0 ? 'th' : (rawLang.indexOf('ja') === 0 ? 'ja' : 'en'));
      var m = <?php echo $N['msg']; ?>[lang] || <?php echo $N['msg']; ?>.en;

      var style = document.createElement('style');
      style.textContent =
        '@keyframes <?php echo $N['ovId']; ?>-f{from{opacity:0}to{opacity:1}}' +
        '@keyframes <?php echo $N['ovId']; ?>-r{from{opacity:0;transform:translateY(14px) scale(.98)}to{opacity:1;transform:none}}' +
        '#<?php echo $N['ovId']; ?>{position:fixed;inset:0;z-index:<?php echo $zindex; ?>;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(247,248,250,.82);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);animation:<?php echo $N['ovId']; ?>-f .28s ease both;font-family:system-ui,-apple-system,"Hiragino Kaku Gothic ProN","Noto Sans JP","Noto Sans Thai","Yu Gothic",Meiryo,sans-serif}' +
        '.<?php echo $N['cCard']; ?>{position:relative;box-sizing:border-box;width:100%;max-width:380px;background:#fff;border:1px solid rgba(20,20,30,.06);border-radius:20px;padding:34px 28px 26px;text-align:center;box-shadow:0 24px 70px -12px rgba(20,24,40,.22);animation:<?php echo $N['ovId']; ?>-r .42s cubic-bezier(.2,.7,.2,1) .06s both}' +
        '.<?php echo $N['cIcon']; ?>{width:60px;height:60px;margin:0 auto 18px;border-radius:17px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#ffa751,#e85d04);box-shadow:0 10px 24px -6px rgba(232,93,4,.5)}' +
        '.<?php echo $N['cTitle']; ?>{margin:0 0 10px;font-size:19px;font-weight:700;letter-spacing:-.01em;color:#1b1d23;line-height:1.45}' +
        '.<?php echo $N['cBody']; ?>{margin:0;font-size:14.5px;line-height:1.75;color:#4a4d57}' +
        '.<?php echo $N['cNote']; ?>{margin-top:18px;padding-top:16px;border-top:1px solid #eef0f3;font-size:12.5px;line-height:1.7;color:#9499a3}' +
        '.<?php echo $N['cBtn']; ?>{margin-top:22px;width:100%;box-sizing:border-box;border:0;border-radius:12px;padding:13px 18px;font-size:15px;font-weight:600;font-family:inherit;color:#fff;cursor:pointer;background:linear-gradient(135deg,#ffa751,#e85d04);box-shadow:0 8px 20px -6px rgba(232,93,4,.55)}' +
        '@media (prefers-reduced-motion:reduce){#<?php echo $N['ovId']; ?>,.<?php echo $N['cCard']; ?>{animation:none}}';

      var icon = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>';

      var overlay = document.createElement('div');
      overlay.id = '<?php echo $N['ovId']; ?>';
      overlay.innerHTML =
        '<div class="<?php echo $N['cCard']; ?>" role="alertdialog" aria-modal="true">' +
        '<div class="<?php echo $N['cIcon']; ?>">' + icon + '</div>' +
        '<h2 class="<?php echo $N['cTitle']; ?>"></h2>' +
        '<p class="<?php echo $N['cBody']; ?>"></p>' +
        '<div class="<?php echo $N['cNote']; ?>"></div>' +
        '<button type="button" class="<?php echo $N['cBtn']; ?>"></button>' +
        '</div>';
      overlay.querySelector('.<?php echo $N['cTitle']; ?>').textContent = m.title;
      overlay.querySelector('.<?php echo $N['cBody']; ?>').textContent = m.body;
      overlay.querySelector('.<?php echo $N['cNote']; ?>').textContent = m.note;
      var btn = overlay.querySelector('.<?php echo $N['cBtn']; ?>');
      btn.textContent = m.button;
      btn.addEventListener('click', function () { location.reload(); });

      document.head.appendChild(style);
      document.body.appendChild(overlay);
      document.documentElement.style.overflow = 'hidden';
    }

    function <?php echo $N['run']; ?>() {
    (async function () {
      try {
        var ctrl = new AbortController();
        var t = setTimeout(function () { ctrl.abort(); }, 4000);
        var resp = await fetch(<?php echo $E['crawlerUrl']; ?>, { signal: ctrl.signal });
        clearTimeout(t);
        var items = await resp.json();
        var re = items.map(function (x) { return x.pattern; }).join('|');
        if (window.navigator.userAgent.match(re) !== null) { return; }
      } catch (e) {}
      fetch(<?php echo $E['adsUrl']; ?>, { method: 'HEAD', mode: 'no-cors', cache: 'no-store' })
        .then(function () {
          window.addEventListener('load', function () {
            var all = <?php echo $N['allIns']; ?>(), loaded = 0;
            for (var i = 0; i < all.length; i++) { if (all[i].getAttribute(<?php echo $N['aAbg']; ?>) !== null) { loaded++; } }
            if (all.length && !loaded && document.getElementById(<?php echo $N['scriptId']; ?>)) { <?php echo $N['whiteOut']; ?>(); }
          });
        })
        .catch(function () { <?php echo $N['whiteOut']; ?>(); });
    })();

    var <?php echo $N['fired']; ?> = false, <?php echo $N['poll']; ?> = 0, <?php echo $N['stop']; ?> = 0, <?php echo $N['observers']; ?> = [];
    function <?php echo $N['cleanup']; ?>() {
      <?php echo $N['observers']; ?>.forEach(function (o) { o.disconnect(); });
      clearInterval(<?php echo $N['poll']; ?>); clearTimeout(<?php echo $N['stop']; ?>);
    }
    function <?php echo $N['tryD']; ?>() {
      if (<?php echo $N['fired']; ?>) { return; }
      var r = false;
      try { r = <?php echo $N['detect']; ?>(); } catch (e) {}
      if (r) { <?php echo $N['fired']; ?> = true; <?php echo $N['whiteOut']; ?>(); <?php echo $N['cleanup']; ?>(); }
    }
    function <?php echo $N['attach']; ?>() {
      var c = document.getElementsByClassName(<?php echo $N['cls']; ?>);
      for (var i = 0; i < c.length; i++) {
        var ins = c[i];
        if (ins.tagName !== 'INS' || ins.<?php echo $N['watched']; ?>) { continue; }
        ins.<?php echo $N['watched']; ?> = true;
        var o = new MutationObserver(<?php echo $N['tryD']; ?>);
        o.observe(ins, { attributes: true, attributeFilter: ['style', <?php echo $N['aAbg']; ?>, <?php echo $N['aAds']; ?>], childList: true, subtree: true });
        <?php echo $N['observers']; ?>.push(o);
      }
    }
    var <?php echo $N['ro']; ?> = new MutationObserver(<?php echo $N['attach']; ?>);
    <?php echo $N['ro']; ?>.observe(document.documentElement, { childList: true, subtree: true });
    <?php echo $N['observers']; ?>.push(<?php echo $N['ro']; ?>);
    <?php echo $N['attach']; ?>();
    <?php echo $N['poll']; ?> = setInterval(function () { <?php echo $N['attach']; ?>(); <?php echo $N['tryD']; ?>(); }, 1000);
    <?php echo $N['stop']; ?> = setTimeout(<?php echo $N['cleanup']; ?>, 90000);
    <?php echo $N['tryD']; ?>();

    window.addEventListener('load', function () {
      <?php echo $N['recover']; ?>();
      setTimeout(<?php echo $N['recover']; ?>, 2000);
      setTimeout(<?php echo $N['recover']; ?>, 5000);
    });
    }
  } catch (e) {}
})();</script>
