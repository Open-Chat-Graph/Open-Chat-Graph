// 表示言語ごとの文面。<html lang> に合わせて出し分ける。
// 誤検知の可能性を考慮し、非難調を避けて「再読み込み」導線を添える。
const ADBLOCK_NOTICE_MESSAGES = {
  ja: {
    title: '広告が表示されていません',
    body: 'オプチャグラフは広告収入で運営されています。広告ブロッカーをご利用の場合は、解除するか当サイトを許可リストに追加してください。',
    note: '広告ブロッカーを使用していないのにこの画面が表示される場合は、お手数ですがページの再読み込みをお試しください。',
    button: '再読み込み',
  },
  zh: {
    title: '廣告未能顯示',
    body: '本網站以廣告收入維持營運。如果您正在使用廣告攔截器，請將本站加入白名單或暫時停用。',
    note: '若您並未使用廣告攔截器卻看到此畫面，請嘗試重新整理頁面。',
    button: '重新整理',
  },
  th: {
    title: 'ไม่สามารถแสดงโฆษณาได้',
    body: 'เว็บไซต์นี้ดำเนินการด้วยรายได้จากโฆษณา หากคุณกำลังใช้ตัวบล็อกโฆษณา โปรดปิดการใช้งานหรือเพิ่มเว็บไซต์นี้ในรายการที่อนุญาต',
    note: 'หากคุณไม่ได้ใช้ตัวบล็อกโฆษณาแต่ยังเห็นหน้านี้ โปรดลองโหลดหน้าเว็บใหม่อีกครั้ง',
    button: 'โหลดหน้าใหม่',
  },
  en: {
    title: 'Ads aren’t loading',
    body: 'OpenChat Graph is supported by ad revenue. If you’re using an ad blocker, please disable it or add this site to your allowlist.',
    note: 'If you’re not using an ad blocker and still see this screen, please try reloading the page.',
    button: 'Reload',
  },
}

// GA4 計測(GTM経由)。アドブロック利用者の多くは GA/GTM ドメインごと遮断するため、
// 「ブロックされた瞬間」の送信はそもそも届かない。そこで送信せずローカルに記録だけ残し、
// 後日 広告が正常表示された(=ブロッカー解除/許可リスト追加された)訪問で初めて送る。
// → 「ブロック → 解除」に転じた数(=オーバーレイが効いて広告を見るようになった数)が取れる。
//
// 送信は GTM の dataLayer 経由。GA4 ID(G-DBS3CW3XH5)はこのサイトでは GTM が所有しており、
// 自前で gtag.js を読み込んで直送してもイベントが弾かれて GA4 に届かない(検証済み)。そのため
// window.dataLayer に event を push し、GTM 側の「カスタムイベント トリガー(adblock_recovered)」
// ＋「GA4 イベントタグ」で GA4 へ転送する。
const BLOCKED_FLAG_KEY = 'ocgab_blocked_at'
const RECOVERY_EVENT_NAME = 'adblock_recovered'

// 検出した端末にフラグ(初回ブロック時刻)を残す。送信はしない(この瞬間は届かない前提)。
function markBlockedLocally() {
  try {
    if (!localStorage.getItem(BLOCKED_FLAG_KEY)) {
      localStorage.setItem(BLOCKED_FLAG_KEY, String(Date.now()))
    }
  } catch (e) {}
}

// GTM の dataLayer にカスタムイベントを push する(GTM タグが GA4 へ転送)。本番ホストのみ。
function pushDataLayerEvent(name, params) {
  if (location.hostname !== 'openchat-review.me') return
  try {
    window.dataLayer = window.dataLayer || []
    window.dataLayer.push(Object.assign({ event: name }, params || {}))
  } catch (e) {}
}

// 広告が実際に表示されているか(潰されていない iframe が1枚以上ある)
function adsAreRendering() {
  let filled = 0
  document
    .querySelectorAll('.adsbygoogle[data-adsbygoogle-status="done"]')
    .forEach((el) => {
      if (el.getAttribute('data-ad-status') === 'unfilled') return
      const iframe = el.querySelector('iframe')
      if (!iframe) return
      const style = window.getComputedStyle(iframe)
      if (parseFloat(style.width) > 1 && parseFloat(style.height) > 1) filled++
    })
  return filled > 0
}

// 前回ブロックされた端末で、今回 広告が正常表示された=解除された とみなし送信する。
function reportRecoveryIfNeeded() {
  let raw
  try {
    raw = localStorage.getItem(BLOCKED_FLAG_KEY)
  } catch (e) {
    return
  }
  if (!raw) return
  // まだ広告が出ていない / まだ潰されている間はフラグを触らず見送る(次の機会に再判定)
  if (!adsAreRendering() || detectAdBlock()) return

  const blockedAt = parseInt(raw, 10)
  const elapsedMs = Date.now() - blockedAt
  const THIRTY_DAYS = 30 * 24 * 3600 * 1000

  // 不正値・古すぎる(30日超)は帰属できないので送らずフラグだけ掃除する
  if (!(blockedAt > 0) || elapsedMs > THIRTY_DAYS) {
    try {
      localStorage.removeItem(BLOCKED_FLAG_KEY)
    } catch (e) {}
    return
  }

  // 経過時間バケット(端末時計のズレで負になっても最短扱いにして送る)
  const bucket =
    elapsedMs < 30 * 60 * 1000
      ? 'within_30min'
      : elapsedMs < 24 * 3600 * 1000
      ? 'within_1day'
      : 'within_30days'

  // 送信してからフラグを消す(送信前に消すと、未送信のまま消える事故が起きるため)
  pushDataLayerEvent(RECOVERY_EVENT_NAME, { recovery_elapsed: bucket })
  try {
    localStorage.removeItem(BLOCKED_FLAG_KEY)
  } catch (e) {}
}

function whiteOut() {
  // 多重表示を防ぐ
  if (document.getElementById('ocgab-overlay')) return

  // この端末は「ブロックされた」とローカルに記録(後日の解除検出に使う)
  markBlockedLocally()

  // 現在の表示言語を <html lang> から判定 (ja / zh-TW → zh / th / その他 → en)
  const rawLang = (document.documentElement.lang || 'ja').toLowerCase()
  const lang = rawLang.startsWith('zh')
    ? 'zh'
    : rawLang.startsWith('th')
    ? 'th'
    : rawLang.startsWith('ja')
    ? 'ja'
    : 'en'
  const msg = ADBLOCK_NOTICE_MESSAGES[lang] || ADBLOCK_NOTICE_MESSAGES.en

  const style = document.createElement('style')
  style.textContent = `
    @keyframes ocgab-fade { from { opacity: 0 } to { opacity: 1 } }
    @keyframes ocgab-rise { from { opacity: 0; transform: translateY(14px) scale(.98) } to { opacity: 1; transform: none } }
    #ocgab-overlay {
      position: fixed; inset: 0; z-index: 2147483647;
      display: flex; align-items: center; justify-content: center; padding: 24px;
      background: rgba(247, 248, 250, .82);
      -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
      animation: ocgab-fade .28s ease both;
      font-family: system-ui, -apple-system, "Hiragino Kaku Gothic ProN", "Noto Sans JP", "Noto Sans Thai", "Yu Gothic", Meiryo, sans-serif;
    }
    #ocgab-card {
      position: relative; box-sizing: border-box; width: 100%; max-width: 380px;
      background: #fff; border: 1px solid rgba(20, 20, 30, .06); border-radius: 20px;
      padding: 34px 28px 26px; text-align: center;
      box-shadow: 0 24px 70px -12px rgba(20, 24, 40, .22);
      animation: ocgab-rise .42s cubic-bezier(.2, .7, .2, 1) .06s both;
    }
    #ocgab-card .ocgab-icon {
      width: 60px; height: 60px; margin: 0 auto 18px; border-radius: 17px;
      display: flex; align-items: center; justify-content: center;
      background: linear-gradient(135deg, #ffa751, #e85d04);
      box-shadow: 0 10px 24px -6px rgba(232, 93, 4, .5);
    }
    #ocgab-card .ocgab-title {
      margin: 0 0 10px; font-size: 19px; font-weight: 700; letter-spacing: -.01em;
      color: #1b1d23; line-height: 1.45;
    }
    #ocgab-card .ocgab-body { margin: 0; font-size: 14.5px; line-height: 1.75; color: #4a4d57; }
    #ocgab-card .ocgab-note {
      margin-top: 18px; padding-top: 16px; border-top: 1px solid #eef0f3;
      font-size: 12.5px; line-height: 1.7; color: #9499a3;
    }
    #ocgab-card .ocgab-btn {
      margin-top: 22px; width: 100%; box-sizing: border-box; border: 0; border-radius: 12px;
      padding: 13px 18px; font-size: 15px; font-weight: 600; font-family: inherit;
      color: #fff; cursor: pointer; background: linear-gradient(135deg, #ffa751, #e85d04);
      box-shadow: 0 8px 20px -6px rgba(232, 93, 4, .55);
      transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
    }
    #ocgab-card .ocgab-btn:hover { transform: translateY(-1px); filter: brightness(1.03); box-shadow: 0 12px 26px -6px rgba(232, 93, 4, .6); }
    #ocgab-card .ocgab-btn:active { transform: translateY(0) }
    @media (prefers-reduced-motion: reduce) {
      #ocgab-overlay, #ocgab-card { animation: none }
    }
  `

  const icon =
    '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
    '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>'

  const overlay = document.createElement('div')
  overlay.id = 'ocgab-overlay'
  overlay.innerHTML =
    '<div id="ocgab-card" role="alertdialog" aria-modal="true">' +
    '<div class="ocgab-icon">' + icon + '</div>' +
    '<h2 class="ocgab-title"></h2>' +
    '<p class="ocgab-body"></p>' +
    '<div class="ocgab-note"></div>' +
    '<button type="button" class="ocgab-btn"></button>' +
    '</div>'

  overlay.querySelector('.ocgab-title').textContent = msg.title
  overlay.querySelector('.ocgab-body').textContent = msg.body
  overlay.querySelector('.ocgab-note').textContent = msg.note
  const btn = overlay.querySelector('.ocgab-btn')
  btn.textContent = msg.button
  btn.addEventListener('click', function () {
    location.reload()
  })

  document.head.appendChild(style)
  document.body.appendChild(overlay)
  document.documentElement.style.overflow = 'hidden'
}

async function blockblock() {
  // クローラ(bot)には案内を出さないための UA 判定。判定用リストの取得が
  // 失敗・タイムアウトしても、広告ブロック検出本体(下の HEAD fetch)は必ず走らせる。
  //   ※ 以前はこの取得を素の await で行っていたため、リスト取得が失敗/ハングすると
  //     関数ごと止まり、ホストブロック(hosts/DNS で googlesyndication を遮断)時に
  //     whiteOut まで到達しなかった。try/catch + タイムアウトで素通りさせて修正。
  try {
    const agentsJsonUrl =
      'https://raw.githubusercontent.com/monperrus/crawler-user-agents/master/crawler-user-agents.json'
    const ctrl = new AbortController()
    const timer = setTimeout(() => ctrl.abort(), 4000)
    const response = await fetch(agentsJsonUrl, { signal: ctrl.signal })
    clearTimeout(timer)
    const items = await response.json()
    const REGEX_CRAWLER = items.map((item) => item.pattern).join('|')
    if (window.navigator.userAgent.match(REGEX_CRAWLER) !== null) return
  } catch (e) {
    // リスト取得失敗時は通常ユーザーとして扱い、検出を継続する
  }

  fetch('https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js', {
    method: 'HEAD',
    mode: 'no-cors',
    cache: 'no-store',
  })
    .then(() => {
      console.log('adsbygoogle.js is loaded')
      window.addEventListener('load', function () {
        const loadedAds = []
        document.querySelectorAll('ins.adsbygoogle').forEach(function (el) {
          el.attributes['data-adsbygoogle-status'] && loadedAds.push(el)
        })

        document.querySelectorAll('ins.adsbygoogle').length &&
          !loadedAds.length &&
          document.getElementById('ads-by-google-script') &&
          whiteOut()
      })
    })
    .catch((err) => {
      whiteOut()
    })
}

const admin = document.cookie
  .split('; ')
  .find((row) => row.startsWith('admin-enable='))
  ?.split('=')[1]
admin && console.log('admin:', admin)

if (admin) {
  const gearBtn = document.getElementById('admin-gear-btn')
  if (gearBtn) gearBtn.style.display = 'flex'
}

if (typeof admin === 'undefined' || !admin) blockblock()

function detectAdBlock() {
  // 広告枠の処理が終わった(data-adsbygoogle-status="done")要素だけを対象にする
  const adElements = document.querySelectorAll(
    '.adsbygoogle[data-adsbygoogle-status="done"]'
  )

  if (adElements.length === 0) {
    return false
  }

  let totalCount = 0
  let blockedCount = 0

  adElements.forEach((adElement) => {
    // Google が「広告在庫なし(未配信)」と判定した枠は無罪。検出対象から外す。
    // ※ アドブロック対策で誤って通常ユーザーをブロックしないための最重要ガード
    if (adElement.getAttribute('data-ad-status') === 'unfilled') {
      return
    }

    totalCount++

    const iframe = adElement.querySelector('iframe')
    // iframe が無い枠はブロック扱いしない(未配信などの無罪ケースを守る)
    if (!iframe) {
      return
    }

    const style = window.getComputedStyle(iframe)
    const width = parseFloat(style.width)
    const height = parseFloat(style.height)

    // (A) 描画サイズが 1px 以下に潰されている
    //     (0px や auto=NaN の「未描画/ロード途中」は誤爆しないよう除外)
    const collapsedByComputed = width > 0 && width <= 1 && height > 0 && height <= 1

    // (B) iframe の inline style に 1px !important が注入されている
    //     (uBlock Origin 等が広告を潰すときの指紋。通常のAdSenseは付与しない)
    //     max-width / max-height を width / height と誤認しないよう語境界で判定する
    const styleAttr = iframe.getAttribute('style') || ''
    const collapsedByInline =
      /(^|[;\s])width:\s*1px\s*!important/.test(styleAttr) &&
      /(^|[;\s])height:\s*1px\s*!important/.test(styleAttr)

    // (C) uBlock Origin / uBO Lite が 2026-06-15 のシム刷新(uBOLite 2026.621〜・
    //     Chromium版。gorhill/uBlock コミット f5be2bbed + 89655c3f8)で導入した
    //     新サロゲートへの対策。旧来の「iframe を 1px に潰す」手法をやめ、通常サイズの
    //     空 iframe を作って data-ad-status="filled" を立て「配信済み広告」に偽装する
    //     ようになった(中身は空の data: URL、iframe には src 属性も inline style も
    //     付けない)。本物の AdSense は filled なら必ず googleads/doubleclick の src を
    //     持ち、iframe に inline style(border・サイズ等)も付与する。その両方を欠いた
    //     「filled を名乗る空 iframe」はこの新サロゲート以外に存在しないので検出する。
    //     ※ no fill(unfilled)は上で除外済み。誤検知防止のため「filled 明示」を必須にする。
    const fakeFilledBySurrogate =
      adElement.getAttribute('data-ad-status') === 'filled' &&
      (iframe.getAttribute('src') || '') === '' &&
      styleAttr.trim() === ''

    if (collapsedByComputed || collapsedByInline || fakeFilledBySurrogate) {
      blockedCount++
    }
  })

  // 配信対象の枠の過半数が潰されていればアドブロックと判定
  return totalCount > 0 && blockedCount / totalCount >= 0.5
}

if (typeof admin === 'undefined' || !admin) {
  // 広告ブロッカーが広告枠を潰す/隠した瞬間を検出する監視。
  //
  // なぜ MutationObserver 主軸か:
  // - setInterval によるポーリングは「タブが裏に回ると凍結する」(ブラウザの
  //   Page Visibility 仕様で隠れたタブのタイマーは大幅に間引かれる)。ユーザーが
  //   別タブ/別ウィンドウ/DevTools に移った隙に潰されると取りこぼし、待っても
  //   いつまでも出ない、という不具合になっていた。
  // - 潰しのタイミングは環境差が大きい(即時〜十数秒)。固定窓のポーリングは
  //   原理的に不安定。
  // MutationObserver はタイマーではなく DOM の変化で発火するため、タブが裏でも
  // ブロッカーが iframe を 1px に潰す/枠を隠した瞬間に即座に判定でき、取りこぼさない。
  // ポーリングは前面時の保険として併用する。
  //
  // ※ 検出条件(detectAdBlock)は 1px!important 指紋等ブロッカー固有のもののみで、
  //   no fill(unfilled)やiframe無し枠は除外しているため誤検知しない。
  let fired = false
  let pollInterval = 0
  let stopTimeout = 0
  const observers = []

  const cleanup = () => {
    observers.forEach((o) => o.disconnect())
    clearInterval(pollInterval)
    clearTimeout(stopTimeout)
  }

  const tryDetect = () => {
    if (fired) return
    if (detectAdBlock()) {
      fired = true
      whiteOut()
      cleanup()
    }
  }

  // 各広告枠に観測を張る(iframe追加・style注入・status変化で即判定)
  const attachToSlots = () => {
    document.querySelectorAll('ins.adsbygoogle').forEach((ins) => {
      if (ins.__ocgabWatched) return
      ins.__ocgabWatched = true
      const o = new MutationObserver(tryDetect)
      o.observe(ins, {
        attributes: true,
        attributeFilter: ['style', 'data-adsbygoogle-status', 'data-ad-status'],
        childList: true,
        subtree: true,
      })
      observers.push(o)
    })
  }

  // 後から差し込まれる広告枠も拾えるよう、枠の追加を監視して観測を張り直す
  const rootObserver = new MutationObserver(attachToSlots)
  rootObserver.observe(document.documentElement, { childList: true, subtree: true })
  observers.push(rootObserver)
  attachToSlots()

  // 前面時のフォールバック・ポーリング(observerの保険)
  pollInterval = setInterval(() => {
    attachToSlots()
    tryDetect()
  }, 1000)

  // 90秒で監視終了(潰されない通常ユーザーはここで終了し誤検知しない)
  stopTimeout = setTimeout(cleanup, 90000)

  // すでに潰れている場合に備えた初回チェック
  tryDetect()
  console.log('AdBlock検出の監視を開始しました')
}

if (typeof admin === 'undefined' || !admin) {
  // 「ブロック → 解除」計測。前回ブロックされた端末で広告が正常表示されたら GA に送る。
  // 広告は遅延描画されるので load 後に数回リトライ(送信は一度きり: フラグ削除で以後no-op)。
  window.addEventListener('load', function () {
    reportRecoveryIfNeeded()
    setTimeout(reportRecoveryIfNeeded, 2000)
    setTimeout(reportRecoveryIfNeeded, 5000)
  })
}
