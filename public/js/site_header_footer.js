function validateStringNotEmpty(str) {
  const normalizedStr = str.normalize("NFKC");
  const string = normalizedStr.replace(/[\u200B-\u200D\uFEFF]/g, "");
  return string.trim() !== "";
}

(function () {
  const siteHeader = document.getElementById("site_header");
  const searchInput = document.getElementById("q");
  const searchBtn = document.getElementById("search_button");
  const backdrop = document.getElementById("backdrop");

  searchBtn.addEventListener("click", () => {
    siteHeader.classList.add("is-search-form-open");
    searchInput.focus();
  });
  backdrop.addEventListener("click", () => {
    siteHeader.classList.remove("is-search-form-open");
    searchInput.value = "";
  });

  searchInput.addEventListener("keydown", (e) => {
    if (e.key === "Escape" || e.key === "Tab") {
      siteHeader.classList.remove("is-search-form-open");
      e.key === "Escape" && searchBtn.focus();
    }
  });

  document.querySelectorAll(".search-form-inner").forEach(function (el) {
    el.addEventListener("submit", (e) => {
      if (!validateStringNotEmpty(e.target.elements["q"].value))
        e.preventDefault();
    });
  });
})();

const setHeaderShow = (header, hidden, show) => {
  // 現在の位置を保持
  let currentPosition = 0;

  window.addEventListener("scroll", () => {
    // スクロール位置を保持
    let scrollPosition = document.documentElement.scrollTop;

    // スクロールに合わせて要素をヘッダーの高さ分だけ移動（表示域から隠したり表示したり）
    if (scrollPosition <= 48) {
      header.style.transform = `translate(0, ${show})`;
    } else if (currentPosition <= scrollPosition) {
      header.style.transform = "translate(0," + hidden + "px)";
    } else if (currentPosition > scrollPosition) {
      header.style.transform = `translate(0, ${show})`;
    }

    currentPosition = document.documentElement.scrollTop;
  });
};

let pcAdBarGlobal = null;
let bodyPaddingTop = 0;
const setHeaderShow2 = (header, hidden, show) => {
  // 現在の位置を保持
  let currentPosition = 0;

  window.addEventListener("scroll", () => {
    // スクロール位置を保持
    let scrollPosition = document.documentElement.scrollTop;

    if (!pcAdBarGlobal) {
      const pcAdBar = document.querySelector(
        ".adsbygoogle-noablate[data-anchor-status]"
      );
      if (pcAdBar && pcAdBar.style.top === "0px") {
        pcAdBarGlobal = pcAdBar;
        pcAdBarGlobal.style.transition = "all 0.3s";
        const bodyPadTop = document.body.style.paddingTop;
        header.style.top = "";
      }
    }

    // スクロールに合わせて要素をヘッダーの高さ分だけ移動（表示域から隠したり表示したり）
    if (scrollPosition <= 48) {
      header.style.transform = `translate(0, ${show})`;
      if (pcAdBarGlobal && pcAdBarGlobal.style.top === "0px")
        pcAdBarGlobal.style.top = `${hidden * -1}px`;
    } else if (currentPosition <= scrollPosition) {
      header.style.transform = `translate(0, ${hidden}px)`;
      if (pcAdBarGlobal && pcAdBarGlobal.style.top === `${hidden * -1}px`)
        pcAdBarGlobal.style.top = `0px`;
    } else if (currentPosition > scrollPosition) {
      header.style.transform = `translate(0, ${show})`;
      if (pcAdBarGlobal && pcAdBarGlobal.style.top === "0px")
        pcAdBarGlobal.style.top = `${hidden * -1}px`;
    }

    currentPosition = document.documentElement.scrollTop;
  });
};

(() => {
  const header = document.querySelector(".site_header_outer");
  setHeaderShow(header, -48, 0);
})();
