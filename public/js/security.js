function whiteOut() {
  // オーバーレイの作成
  const overlay = document.createElement("div");
  overlay.style.position = "fixed";
  overlay.style.top = "0";
  overlay.style.left = "0";
  overlay.style.width = "100%";
  overlay.style.height = "100%";
  overlay.style.backgroundColor = "white";
  overlay.style.opacity = "0";
  overlay.style.zIndex = "29";
  overlay.style.transition = "opacity 1s"; // フェードインに3秒かける

  // オーバーレイをbodyに追加
  document.body.appendChild(overlay);

  // フェードインの開始
  setTimeout(function () {
    overlay.style.opacity = "1"; // 3秒後にオーバーレイを完全に表示
  }, 0); // 0秒後に実行（すぐに実行）

  // 全てを真っ白にする
  setTimeout(function () {
    document.body.style.backgroundColor = "white"; // 更に3秒後に背景色を白に変更
  }, 1000); // 3秒後に実行

  alert(
    "Erorr: アドブロックが有効な場合は解除してください。\n如果啟用了 adblock，請停用它。\nหากเปิดใช้งาน adblock ให้ปิดการใช้งาน\nIf you are using adblock, please disable it."
  );
}

async function blockblock() {
  const agentsJsonUrl =
    "https://raw.githubusercontent.com/monperrus/crawler-user-agents/master/crawler-user-agents.json";

  const response = await fetch(agentsJsonUrl);
  const items = await response.json();
  const patterns = items.map((item) => item.pattern);
  const REGEX_CRAWLER = patterns.join("|");
  const ua = window.navigator.userAgent;
  const result = ua.match(REGEX_CRAWLER);
  if (result !== null) return;

  fetch("https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js", {
    method: "HEAD",
    mode: "no-cors",
    cache: "no-store",
  })
    .then(() => {
      console.log("adsbygoogle.js is loaded");
      window.addEventListener("load", function () {
        const loadedAds = [];
        document.querySelectorAll("ins.adsbygoogle").forEach(function (el) {
          el.attributes["data-adsbygoogle-status"] && loadedAds.push(el);
        });

        document.querySelectorAll("ins.adsbygoogle").length &&
          !loadedAds.length &&
          document.getElementById("ads-by-google-script") &&
          whiteOut();
      });
    })
    .catch((err) => {
      whiteOut();
    });
}

if (typeof admin === "undefined" || !admin) blockblock();

function detectAdBlock() {
  // すべてのAdSense要素を取得
  const adElements = document.querySelectorAll(".adsbygoogle");

  if (adElements.length === 0) {
    console.log("AdSense要素が見つかりません");
    return false;
  }

  let blockedCount = 0;
  let totalCount = 0;
  let importantCount = 0;

  adElements.forEach((adElement) => {
    // data-adsbygoogle-status="done" の要素のみチェック
    if (adElement.getAttribute("data-adsbygoogle-status") === "done") {
      totalCount++;

      // iframe内のiframeを探す
      const iframe = adElement.querySelector("iframe");

      if (iframe) {
        const style = window.getComputedStyle(iframe);

        // 1pxに縮小されているかチェック
        const width = parseFloat(style.width);
        const height = parseFloat(style.height);

        if (width === 1 && height === 1) {
          blockedCount++;
          console.log("アドブロック検出: iframe が 1px に縮小されています");
        } else {
          console.log("w", style.width, "h", style.height);
        }

        // style属性でheight: 1px !important; と width: 1px !important; をチェック
        const styleAttr = iframe.getAttribute("style") || "";
        if (
          styleAttr.includes("height: 1px !important;") &&
          styleAttr.includes("width: 1px !important;")
        ) {
          importantCount++;
          console.log(
            "アドブロック検出: iframe に height: 1px !important; と width: 1px !important; が設定されています"
          );
        }
      }
    }
  });

  if (totalCount > 0 && blockedCount / totalCount >= 0.5) {
    return 1;
  } else if (importantCount > 0 && importantCount / totalCount >= 0.5) {
    return 2;
  } else {
    return 0;
  }
}

if (typeof admin === "undefined" || !admin) {
  // （インターバルで監視）
  const checkInterval = setInterval(() => {
    if (
      document.querySelector('.adsbygoogle[data-adsbygoogle-status="done"]')
    ) {
      clearInterval(checkInterval);
      if (detectAdBlock()) {
        whiteOut();
      }
    }
  }, 200); // 200ms間隔でチェック

  // 10秒後には必ず停止
  setTimeout(() => clearInterval(checkInterval), 10000); // 10秒後に停止
  console.log("AdBlock検出の監視を開始しました");
}
/* 
(() => {
  const setAdHeight = (target) => {
    const iframe = target.querySelector("iframe");
    if (iframe) {
      const iframeHeight = iframe.offsetHeight;
      if (iframeHeight > 1) {
        target.style.height = `${iframeHeight}px`;
        target.style.setProperty("height", `${iframeHeight}px`, "important");
        return true;
      }
    }
    return false;
  };

  const waitForValidHeight = (target) => {
    const checkHeight = () => {
      if (!setAdHeight(target)) {
        // data-anchor-status が "displayed" になったら停止
        if (target.getAttribute("data-anchor-status") === "displayed") {
          return;
        }
        requestAnimationFrame(checkHeight);
      }
    };
    checkHeight();
  };

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if (
        mutation.type === "attributes" &&
        mutation.attributeName === "data-anchor-status"
      ) {
        const target = mutation.target;
        if (target.matches('ins.adsbygoogle[data-anchor-status="displayed"]')) {
          setAdHeight(target);
        } else if (
          target.matches(
            'ins.adsbygoogle[data-anchor-status="ready-to-display"]'
          )
        ) {
          waitForValidHeight(target);
        }
      }
    });
  });

  // 既存の要素をチェック
  document
    .querySelectorAll('ins.adsbygoogle[data-anchor-status="displayed"]')
    .forEach((el) => {
      setAdHeight(el);
    });

  document
    .querySelectorAll('ins.adsbygoogle[data-anchor-status="ready-to-display"]')
    .forEach((el) => {
      waitForValidHeight(el);
    });

  // 新しく追加される要素を監視
  observer.observe(document.body, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ["data-anchor-status"],
  });
})();
 */