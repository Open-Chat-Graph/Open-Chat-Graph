<?php
/**
 * おすすめタグ定義 (ja.json) 編集GUI — 管理者専用・日本語専用・ローカル編集用途。
 *
 * バックエンド契約:
 *   $tagJson (string) … 整形済みの ja.json 生文字列
 *   $tagData (array)  … デコード済み連想配列
 *   $_meta            … meta() (title 設定済み・__toString で metaタグ出力)
 *
 * 保存: POST /admin/recommend-tags/save に ja.json 全体を JSON ボディで送信。
 *       CSRF は HttpOnly クッキーを same-origin fetch が自動送信するため追加処理不要。
 *
 * データはサーバ側で安全にエンコードして <script type="application/json"> に埋め込み、
 * クライアントで parse する（<script> 注入防止のため JSON_HEX_* を付与）。
 */
$bootJson = json_encode(
    $tagData,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
);

// カテゴリ番号 → 名前 のマップを作る（AppConfig は「名前 => 番号」なので反転）。
// beforeCategory の見出しで番号だけだと意味不明なため、名前を併記する用途。
$categoryNameMap = [];
foreach ((\App\Config\AppConfig::OPEN_CHAT_CATEGORY[''] ?? []) as $name => $num) {
    $categoryNameMap[(string)$num] = $name;
}
$categoryNameJson = json_encode(
    $categoryNameMap,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
);
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo $_meta ?>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700&family=Zen+Old+Mincho:wght@600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #1c1a17;
            --ink-soft: #4a463f;
            --ink-faint: #8a8478;
            --paper: #f4f0e6;
            --paper-card: #fbf9f2;
            --paper-line: #e2dccb;
            --slate: #20242b;
            --slate-2: #2a2f38;
            --slate-line: #383e49;
            --accent: #c2410c;        /* burnt orange — 優先度/操作の主役色 */
            --accent-soft: #e8a07a;
            --gold: #b08900;
            --teal: #0f766e;
            --danger: #9f1d1d;
            --ok: #15803d;
            --mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
            --sans: 'Zen Kaku Gothic New', system-ui, sans-serif;
            --serif: 'Zen Old Mincho', serif;
            --shadow-card: 0 1px 0 rgba(255,255,255,.6) inset, 0 6px 22px -12px rgba(28,26,23,.4);
        }

        * { box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            margin: 0;
            font-family: var(--sans);
            color: var(--ink);
            background:
                radial-gradient(1200px 600px at 100% -10%, rgba(194,65,12,.06), transparent 60%),
                radial-gradient(900px 500px at -10% 110%, rgba(15,118,110,.05), transparent 55%),
                var(--paper);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* ── トップバー（スレート） ───────────────────────────── */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: linear-gradient(180deg, var(--slate), var(--slate-2));
            color: #f4f0e6;
            border-bottom: 1px solid var(--slate-line);
            box-shadow: 0 10px 30px -18px rgba(0,0,0,.7);
        }
        .topbar__inner {
            max-width: 1080px;
            margin: 0 auto;
            padding: 14px 22px;
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .brand { display: flex; align-items: baseline; gap: 12px; min-width: 0; }
        .brand__mark {
            font-family: var(--serif);
            font-size: 22px;
            letter-spacing: .04em;
            font-weight: 700;
            white-space: nowrap;
        }
        .brand__mark::before {
            content: "❡";
            color: var(--accent-soft);
            margin-right: 8px;
            font-weight: 400;
        }
        .brand__sub {
            font-size: 11px;
            color: #b9b3a4;
            font-family: var(--mono);
            letter-spacing: .02em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .topbar__spacer { flex: 1; }
        .topbar__status {
            font-family: var(--mono);
            font-size: 12px;
            color: #9aa1ac;
            display: flex;
            align-items: center;
            gap: 7px;
            white-space: nowrap;
        }
        .topbar__status .dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #54606e; transition: background .2s, box-shadow .2s;
        }
        .topbar__status.dirty .dot { background: var(--gold); box-shadow: 0 0 0 3px rgba(176,137,0,.22); }
        .topbar__status.saved .dot { background: #34d399; box-shadow: 0 0 0 3px rgba(52,211,153,.22); }

        .btn-save {
            font-family: var(--sans);
            font-weight: 700;
            font-size: 14px;
            color: #fff;
            background: linear-gradient(180deg, #d4500f, var(--accent));
            border: 1px solid #a8370a;
            border-radius: 9px;
            padding: 10px 20px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 1px 0 rgba(255,255,255,.25) inset, 0 8px 18px -10px rgba(194,65,12,.9);
            transition: transform .08s ease, filter .15s;
        }
        .btn-save:hover { filter: brightness(1.06); }
        .btn-save:active { transform: translateY(1px); }
        .btn-save:disabled { opacity: .5; cursor: default; filter: none; }
        .btn-save kbd {
            font-family: var(--mono); font-size: 10px; opacity: .8;
            border: 1px solid rgba(255,255,255,.35); border-radius: 4px; padding: 1px 4px;
        }

        /* ── レイアウト ─────────────────────────────────────── */
        .wrap { max-width: 1080px; margin: 0 auto; padding: 26px 22px 120px; }

        .lede {
            font-family: var(--serif);
            font-size: 15px;
            color: var(--ink-soft);
            margin: 6px 0 22px;
            line-height: 1.8;
        }
        .lede b { color: var(--accent); font-weight: 700; }

        .note {
            display: flex; gap: 10px;
            background: #fff8ec;
            border: 1px solid #ecd9a8;
            border-left: 4px solid var(--gold);
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 13px;
            color: #6b5512;
            margin-bottom: 26px;
        }
        .note code { font-family: var(--mono); background: #f3e6c4; padding: 1px 5px; border-radius: 4px; }

        /* ── キーワードの書き方ヘルプ（凡例） ───────────────── */
        .legend {
            background: var(--paper-card);
            border: 1px solid var(--paper-line);
            border-radius: 10px;
            box-shadow: var(--shadow-card);
            margin-bottom: 22px;
            overflow: hidden;
        }
        .legend > summary {
            list-style: none;
            cursor: pointer;
            display: flex; align-items: center; gap: 10px;
            padding: 12px 16px;
            font-family: var(--serif);
            font-size: 15px; font-weight: 700; color: var(--ink);
            user-select: none;
        }
        .legend > summary::-webkit-details-marker { display: none; }
        .legend > summary::before {
            content: "?";
            flex: 0 0 auto;
            width: 22px; height: 22px;
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--accent); color: #fff;
            font-family: var(--mono); font-weight: 700; font-size: 13px;
            border-radius: 50%;
        }
        .legend > summary .legend__hint {
            margin-left: auto;
            font-family: var(--mono); font-size: 11px; font-weight: 400;
            color: var(--ink-faint);
        }
        .legend > summary .legend__hint::after { content: " ▾"; color: var(--accent); }
        .legend[open] > summary .legend__hint::after { content: " ▴"; }
        .legend__body {
            padding: 4px 18px 18px;
            border-top: 1px dashed var(--paper-line);
        }
        .legend__lead {
            font-size: 13px; color: var(--ink-soft); line-height: 1.8;
            margin: 14px 0 16px;
        }
        .legend__lead code { font-family: var(--mono); background: #efe9da; padding: 1px 6px; border-radius: 4px; color: var(--ink); }
        .legend dl { margin: 0; display: grid; grid-template-columns: max-content 1fr; gap: 10px 16px; align-items: baseline; }
        .legend dt {
            font-family: var(--mono); font-size: 12.5px; font-weight: 500;
            color: var(--ink); white-space: nowrap;
        }
        .legend dt .op {
            background: #fdeee4; color: #8a3a0e; border: 1px solid #f0c6a8;
            border-radius: 5px; padding: 1px 6px;
        }
        .legend dt .bin {
            background: #efeafc; color: #4a2e8a; border: 1px solid #cfc1f0;
            border-radius: 5px; padding: 1px 6px;
        }
        .legend dd { margin: 0; font-size: 13px; color: var(--ink-soft); line-height: 1.7; }
        .legend dd code, .legend dd .ex {
            font-family: var(--mono); font-size: 12px;
            background: #efe9da; padding: 1px 6px; border-radius: 4px; color: var(--ink);
        }
        .legend dd b { color: var(--accent); font-weight: 700; }
        .legend__colors {
            display: flex; flex-wrap: wrap; gap: 8px;
            margin-top: 18px; padding-top: 14px;
            border-top: 1px dashed var(--paper-line);
        }
        .legend__colors .ttl { font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--ink-faint); align-self: center; margin-right: 2px; }
        .legend .chip { pointer-events: none; }

        /* ── フィルタ項目の説明ラベル ───────────────────────── */
        .filter-desc {
            font-size: 13px; color: var(--ink-soft); line-height: 1.75;
            margin: 8px 0 10px;
            padding-left: 12px;
            border-left: 3px solid var(--paper-line);
        }
        .filter-desc code { font-family: var(--mono); font-size: 12px; background: #efe9da; padding: 1px 5px; border-radius: 4px; color: var(--ink); }
        .filter-desc b { color: var(--accent); font-weight: 700; }
        .filter-empty {
            display: inline-block; margin-left: 6px;
            font-family: var(--mono); font-size: 10.5px; font-weight: 700;
            letter-spacing: .03em; color: var(--ink-faint);
            background: var(--paper-line); border-radius: 999px; padding: 1px 9px;
            vertical-align: middle;
        }

        /* ── タブ ───────────────────────────────────────────── */
        .tabs { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 20px; border-bottom: 1px solid var(--paper-line); }
        .tab {
            font-family: var(--sans);
            font-size: 13.5px;
            font-weight: 500;
            color: var(--ink-faint);
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            padding: 9px 14px 11px;
            cursor: pointer;
            margin-bottom: -1px;
            transition: color .15s, border-color .15s;
            white-space: nowrap;
        }
        .tab:hover { color: var(--ink-soft); }
        .tab.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 700; }
        .tab .count {
            font-family: var(--mono); font-size: 10px; color: var(--ink-faint);
            background: var(--paper-line); border-radius: 999px; padding: 1px 7px; margin-left: 6px;
        }
        .tab.active .count { background: #f1d6c6; color: var(--accent); }

        .panel { display: none; }
        .panel.active { display: block; animation: fade .25s ease; }
        @keyframes fade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

        /* ── グループ見出し ─────────────────────────────────── */
        .group { margin-bottom: 30px; }
        .group__head {
            display: flex; align-items: baseline; gap: 12px; margin-bottom: 4px;
            border-bottom: 2px solid var(--ink);
            padding-bottom: 6px;
        }
        .group__title { font-family: var(--serif); font-size: 19px; font-weight: 700; color: var(--ink); }
        .group__desc { font-size: 12px; color: var(--ink-faint); }
        .group__hint {
            font-size: 12px; color: var(--ink-soft); margin: 8px 0 12px;
            display: flex; align-items: center; gap: 6px;
        }
        .group__hint::before { content: "↕"; color: var(--accent); font-weight: 700; }

        .subcat { margin: 22px 0 14px; }
        .subcat__bar {
            display: flex; align-items: center; gap: 10px;
            background: var(--slate); color: #f4f0e6;
            border-radius: 8px 8px 0 0; padding: 8px 14px;
            font-family: var(--mono); font-size: 13px;
        }
        .subcat__bar .badge {
            background: var(--accent); color: #fff; font-weight: 700;
            border-radius: 6px; padding: 2px 9px; font-size: 12px;
        }
        .subcat__bar .catname {
            font-family: var(--serif); font-size: 14px; font-weight: 700;
            color: #f4f0e6; letter-spacing: .02em;
        }
        .subcat__bar .nm { color: #cfc9ba; margin-left: auto; }

        /* ── タグ行（カード） ───────────────────────────────── */
        .rows { display: flex; flex-direction: column; gap: 8px; }
        .rows.beforecat { border: 1px solid var(--paper-line); border-top: none; border-radius: 0 0 10px 10px; padding: 10px; background: rgba(251,249,242,.5); }

        .row {
            position: relative;
            display: grid;
            grid-template-columns: 34px 26px 1fr;
            gap: 12px;
            align-items: start;
            background: var(--paper-card);
            border: 1px solid var(--paper-line);
            border-radius: 10px;
            padding: 10px 14px 10px 6px;
            box-shadow: var(--shadow-card);
            /* transform はトランジションに含めない: SortableJS がカーソル追従で毎フレーム
               設定する transform をアニメさせると、要素がカーソルから遅れてズレるため。 */
            transition: box-shadow .15s, border-color .15s, opacity .15s;
        }
        .row:hover { border-color: #d3cab0; }
        /* SortableJS の状態クラス（forceFallback 使用時に付与される） */
        .row.sortable-ghost { opacity: .35; }
        .row.sortable-chosen { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(194,65,12,.18), var(--shadow-card); }
        /* ドラッグ追従クローンは transition を完全に切る（カーソルにピタリ追従させる） */
        .row.sortable-drag, .row.sortable-fallback { box-shadow: 0 14px 30px -12px rgba(28,26,23,.55); transition: none !important; }

        .row__rank {
            grid-row: 1 / -1;
            align-self: center;
            text-align: center;
            font-family: var(--mono);
            font-size: 13px;
            font-weight: 500;
            color: var(--ink-faint);
            display: flex; flex-direction: column; align-items: center; gap: 1px;
        }
        /* 順位の数値入力。見た目はバッジのまま、クリックで打ち替え可能 */
        .row__rank .n {
            width: 32px;
            text-align: center;
            font-family: var(--mono);
            font-size: 15px;
            color: var(--accent);
            font-weight: 500;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 6px;
            padding: 1px 0;
            -moz-appearance: textfield;
        }
        .row__rank .n::-webkit-outer-spin-button,
        .row__rank .n::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .row__rank .n:hover { border-color: var(--paper-line); background: #fff; }
        .row__rank .n:focus { outline: none; border-color: var(--accent); background: #fff; box-shadow: 0 0 0 3px rgba(194,65,12,.12); }
        .row__rank .unit { font-size: 11px; }
        .row__handle {
            grid-row: 1 / -1;
            align-self: stretch;
            display: flex; align-items: center; justify-content: center;
            cursor: grab;
            color: var(--ink-faint);
            border-radius: 6px;
            user-select: none;
            touch-action: none;
        }
        .row__handle:hover { color: var(--accent); background: #f1ece0; }
        .row__handle:active { cursor: grabbing; }
        .row__handle svg { width: 14px; height: 22px; }

        .row__body { min-width: 0; display: flex; flex-direction: column; gap: 9px; }

        .field { display: flex; flex-direction: column; gap: 4px; }
        .field__label {
            font-size: 11px; font-weight: 700; letter-spacing: .04em;
            color: var(--ink-faint); text-transform: uppercase;
            display: flex; align-items: center; gap: 6px;
        }
        .field__label .opt { font-weight: 400; text-transform: none; color: #b3ad9f; letter-spacing: 0; }

        .tag-input {
            font-family: var(--sans);
            font-size: 15px;
            font-weight: 700;
            color: var(--ink);
            background: #fff;
            border: 1px solid var(--paper-line);
            border-radius: 7px;
            padding: 8px 11px;
            width: 100%;
        }
        .tag-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(194,65,12,.12); }

        /* キーワードチップ群 */
        .chips {
            display: flex; flex-wrap: wrap; gap: 6px;
            background: #fff; border: 1px solid var(--paper-line); border-radius: 7px;
            padding: 7px; min-height: 40px; align-items: center;
        }
        .chips:focus-within { border-color: var(--teal); box-shadow: 0 0 0 3px rgba(15,118,110,.1); }
        .chip {
            display: inline-flex; align-items: center; gap: 6px;
            background: #e7f1ef; color: #0b4f49;
            border: 1px solid #b9ddd8;
            border-radius: 6px;
            padding: 3px 4px 3px 8px;
            font-family: var(--mono); font-size: 12.5px;
            max-width: 100%;
        }
        .chip span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .chip.kw-op { background: #fdeee4; color: #8a3a0e; border-color: #f0c6a8; }      /* _AND_/_OR_ 含む */
        .chip.kw-bin { background: #efeafc; color: #4a2e8a; border-color: #cfc1f0; }       /* utfbin_ 含む */
        .chip__x {
            border: none; background: transparent; cursor: pointer;
            color: inherit; opacity: .55; font-size: 14px; line-height: 1;
            padding: 0 2px; border-radius: 4px;
        }
        .chip__x:hover { opacity: 1; background: rgba(0,0,0,.08); }
        .chips__input {
            flex: 1; min-width: 90px; border: none; outline: none; background: transparent;
            font-family: var(--mono); font-size: 12.5px; color: var(--ink); padding: 3px 2px;
        }

        .self-kw {
            font-family: var(--mono); font-size: 11.5px; color: var(--ink-faint);
            font-style: normal;
        }

        /* タグ行のアクション群＝本体最下段の小さな横並びボタン（全幅にしない） */
        .row__actions {
            display: flex; flex-flow: row wrap; gap: 6px;
            align-items: center; align-self: start;
            margin-top: 3px; padding-top: 9px;
            border-top: 1px dashed var(--paper-line);
        }
        .icon-btn {
            flex: 0 0 auto;
            border: 1px solid var(--paper-line); background: #fff;
            border-radius: 6px; padding: 4px 10px; cursor: pointer;
            font-size: 12px; font-weight: 500; color: var(--ink-soft); font-family: var(--sans);
            white-space: nowrap; line-height: 1.5;
            transition: background .12s, color .12s, border-color .12s, box-shadow .12s;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .icon-btn .ic { font-size: 12px; line-height: 1; }
        .icon-btn:hover { background: #f3ede0; border-color: #cfc6ab; }
        .icon-btn:active { box-shadow: 0 0 0 3px rgba(28,26,23,.06) inset; }
        /* 改名: 主操作っぽく塗りのアクセント */
        .icon-btn.rename { background: #fdf1ea; border-color: #f0c6a8; color: #9a4012; }
        .icon-btn.rename:hover { background: #fbe6d8; border-color: var(--accent); color: var(--accent); }
        /* 略称/説明: 控えめなアウトライン（ゴールド） */
        .icon-btn.meta:hover { border-color: var(--gold); color: var(--gold); background: #fdf7e6; }
        /* 削除: 控えめな危険色アウトライン */
        .icon-btn.del { border-color: #e6c4c4; color: var(--danger); }
        .icon-btn.del:hover { border-color: var(--danger); color: #fff; background: var(--danger); }
        .icon-btn.del:active { box-shadow: 0 0 0 3px rgba(159,29,29,.18) inset; }

        .has-meta { position: relative; }
        .meta-flag {
            position: absolute; top: -7px; left: 30px;
            background: var(--gold); color: #fff; font-size: 9px; font-weight: 700;
            border-radius: 999px; padding: 1px 8px; letter-spacing: .04em;
            font-family: var(--mono);
        }

        .add-row {
            display: flex; align-items: center; gap: 8px;
            border: 1.5px dashed #cdc4a9; background: transparent;
            border-radius: 10px; padding: 11px 14px; width: 100%;
            cursor: pointer; color: var(--ink-soft); font-family: var(--sans);
            font-size: 13px; font-weight: 500; margin-top: 8px;
            transition: background .12s, border-color .12s, color .12s;
        }
        .add-row:hover { background: #f6f1e4; border-color: var(--accent); color: var(--accent); }
        .add-row::before { content: "＋"; font-weight: 700; }

        /* ── メタ表（略称 / 説明 / リダイレクト） ──────────── */
        .meta-grid { display: flex; flex-direction: column; gap: 10px; }
        .meta-card {
            background: var(--paper-card); border: 1px solid var(--paper-line);
            border-radius: 10px; padding: 12px 14px; box-shadow: var(--shadow-card);
            display: grid; gap: 10px;
        }
        .meta-card.three { grid-template-columns: 1fr 1fr auto; align-items: start; }
        .meta-card.two { grid-template-columns: 1fr auto; align-items: start; }
        .meta-card .lbl { font-size: 11px; font-weight: 700; color: var(--ink-faint); text-transform: uppercase; letter-spacing: .04em; margin-bottom: 3px; }
        .meta-input, .meta-area {
            font-family: var(--sans); font-size: 14px; color: var(--ink);
            background: #fff; border: 1px solid var(--paper-line); border-radius: 7px;
            padding: 7px 10px; width: 100%;
        }
        .meta-input.mono { font-family: var(--mono); font-size: 13px; }
        .meta-area { resize: vertical; min-height: 64px; line-height: 1.6; }
        .meta-input:focus, .meta-area:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(194,65,12,.12); }
        .arrow { align-self: center; color: var(--accent); font-family: var(--mono); font-weight: 700; padding: 0 2px; }

        .raw-area {
            font-family: var(--mono); font-size: 12.5px; line-height: 1.55;
            width: 100%; min-height: 240px; resize: vertical;
            background: var(--slate); color: #e7e2d4;
            border: 1px solid var(--slate-line); border-radius: 10px; padding: 14px;
        }
        .raw-area:focus { outline: none; box-shadow: 0 0 0 3px rgba(194,65,12,.2); }

        .section-head {
            display: flex; align-items: baseline; justify-content: space-between; gap: 12px;
            margin: 4px 0 14px;
        }
        .section-head h2 { font-family: var(--serif); font-size: 19px; margin: 0; }
        .section-head p { font-size: 12px; color: var(--ink-faint); margin: 0; }

        /* ── モーダル（改名 / メタ編集） ───────────────────── */
        .overlay {
            position: fixed; inset: 0; z-index: 100;
            background: rgba(28,26,23,.55); backdrop-filter: blur(2px);
            display: none; align-items: center; justify-content: center; padding: 20px;
        }
        .overlay.open { display: flex; animation: fade .2s ease; }
        .modal {
            background: var(--paper-card); border: 1px solid var(--paper-line);
            border-radius: 14px; width: min(520px, 100%); padding: 22px;
            box-shadow: 0 30px 80px -30px rgba(0,0,0,.6);
        }
        .modal h3 { font-family: var(--serif); font-size: 18px; margin: 0 0 4px; }
        .modal p.sub { font-size: 12.5px; color: var(--ink-faint); margin: 0 0 16px; line-height: 1.6; }
        .modal .field + .field { margin-top: 14px; }
        .modal__warn {
            display: none; margin-top: 12px;
            background: #fbeaea; border: 1px solid #e7baba; color: var(--danger);
            border-radius: 7px; padding: 9px 12px; font-size: 12.5px;
        }
        .modal__warn.show { display: block; }
        .modal__foot { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .modal label.f { font-size: 11px; font-weight: 700; color: var(--ink-faint); text-transform: uppercase; letter-spacing: .04em; }
        .modal .modal-input {
            font-family: var(--sans); font-size: 15px; width: 100%; margin-top: 4px;
            background: #fff; border: 1px solid var(--paper-line); border-radius: 8px; padding: 9px 12px;
        }
        .modal .modal-input.mono { font-family: var(--mono); font-size: 13px; }
        .modal textarea.modal-input { resize: vertical; min-height: 96px; line-height: 1.6; }
        .modal .modal-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(194,65,12,.12); }
        .btn-ghost {
            font-family: var(--sans); font-size: 13.5px; color: var(--ink-soft);
            background: #fff; border: 1px solid var(--paper-line); border-radius: 8px;
            padding: 9px 16px; cursor: pointer;
        }
        .btn-ghost:hover { background: #f1ece0; }
        .btn-primary {
            font-family: var(--sans); font-size: 13.5px; font-weight: 700; color: #fff;
            background: var(--accent); border: 1px solid #a8370a; border-radius: 8px;
            padding: 9px 18px; cursor: pointer;
        }
        .btn-primary:hover { filter: brightness(1.06); }
        .btn-primary.danger { background: var(--danger); border-color: #7a1414; }

        .rename-from { font-family: var(--mono); font-size: 13px; color: var(--ink-soft); background: #f1ece0; border-radius: 7px; padding: 9px 12px; }

        /* ── トースト ───────────────────────────────────────── */
        .toast-wrap { position: fixed; right: 18px; bottom: 18px; z-index: 200; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            font-size: 13.5px; color: #fff; border-radius: 10px; padding: 12px 16px;
            box-shadow: 0 18px 40px -18px rgba(0,0,0,.6); max-width: 380px;
            display: flex; gap: 10px; align-items: flex-start;
            animation: toastIn .25s cubic-bezier(.2,.9,.3,1.2);
        }
        .toast.ok { background: linear-gradient(180deg,#1a9450,var(--ok)); }
        .toast.err { background: linear-gradient(180deg,#b62525,var(--danger)); }
        .toast .ic { font-weight: 700; }
        @keyframes toastIn { from { opacity: 0; transform: translateY(14px) scale(.96); } to { opacity: 1; transform: none; } }

        .empty { font-size: 13px; color: var(--ink-faint); padding: 10px 4px; font-style: italic; }

        @media (max-width: 720px) {
            .row { grid-template-columns: 28px 24px 1fr; }
            .meta-card.three, .meta-card.two { grid-template-columns: 1fr; }
            .arrow { display: none; }
            .topbar__status { display: none; }
            .brand__sub { display: none; }
        }
    </style>
    <script>window.addEventListener('pageshow', function (e) { if (e.persisted) location.reload(); });</script>
</head>

<body>
    <script type="application/json" id="boot-data"><?php echo $bootJson ?></script>
    <script type="application/json" id="category-names"><?php echo $categoryNameJson ?></script>

    <header class="topbar">
        <div class="topbar__inner">
            <div class="brand">
                <span class="brand__mark">おすすめタグ定義</span>
                <span class="brand__sub">app/Services/Recommend/TagDefinition/data/ja.json</span>
            </div>
            <div class="topbar__spacer"></div>
            <div class="topbar__status" id="status">
                <span class="dot"></span><span class="txt">読み込み済み</span>
            </div>
            <button class="btn-save" id="btn-save" type="button">
                保存 <kbd>⌘S</kbd>
            </button>
        </div>
    </header>

    <main class="wrap">
        <p class="lede">
            タグの並び順は<b>そのまま優先度</b>です。上にあるものほど先に判定されます。行をつかんで（左の <span style="font-family:var(--mono)">⠿</span> ハンドル）<b>ドラッグで並べ替え</b>でき、リストの上下端まで運べば<b>自動スクロール</b>します。順位の数字を<b>直接打ち替えてEnter</b>すれば、その位置へ移動します。
        </p>

        <div class="note">
            <span>💾</span>
            <span>保存するとサーバー上の <code>ja.json</code> が直接書き換わります。反映後は <code>git commit</code> で変更を残す運用です。ラベルを改名すると <code>redirects</code> に旧→新が自動追記されます。</span>
        </div>

        <nav class="tabs" id="tabs">
            <button class="tab active" data-tab="keywords">キーワード群</button>
            <button class="tab" data-tab="meta">略称・説明</button>
            <button class="tab" data-tab="redirects">リダイレクト</button>
            <button class="tab" data-tab="filters">フィルタ・並び順</button>
            <button class="tab" data-tab="raw">RAW JSON</button>
        </nav>

        <!-- ───────── キーワード群 ───────── -->
        <section class="panel active" data-panel="keywords">
            <details class="legend">
                <summary>
                    キーワードの書き方
                    <span class="legend__hint">凡例をひらく</span>
                </summary>
                <div class="legend__body">
                    <p class="legend__lead">
                        既定は<b>大文字・小文字を区別しない部分一致</b>です。たとえば <code>AI</code> は <code>ai</code> にもマッチします。
                        演算子をはさむと、複数の語の組み合わせで判定できます。
                    </p>
                    <dl>
                        <dt><span class="op">_OR_</span></dt>
                        <dd>区切った語の<b>いずれか</b>を含めばマッチ。例: <span class="ex">インスタ_OR_Instagram</span></dd>

                        <dt><span class="op">_AND_</span></dt>
                        <dd>区切った語を<b>すべて</b>含めばマッチ。例: <span class="ex">スマホ_AND_ガジェット</span></dd>

                        <dt>優先順位</dt>
                        <dd><span class="ex">_AND_</span> が <span class="ex">_OR_</span> より先に展開されます。<span class="ex">A_AND_B_OR_C</span> は「<b>（A かつ B）または C</b>」の意味になります。</dd>

                        <dt><span class="bin">utfbin_</span></dt>
                        <dd>語の先頭に付けると<b>大文字・小文字／全角・半角を区別する厳密一致</b>になります。例: <span class="ex">utfbin_URA</span> は <span class="ex">ura</span> にはマッチしません。</dd>

                        <dt>絵文字など</dt>
                        <dd>絵文字のような4バイト文字を含む語は、自動的に厳密一致になります。</dd>

                        <dt>未指定のとき</dt>
                        <dd>キーワードを1つも指定しない場合は、<b>タグ名そのもの</b>がキーワードとして使われます。</dd>
                    </dl>
                    <div class="legend__colors">
                        <span class="ttl">チップの色</span>
                        <span class="chip"><span>通常の語</span></span>
                        <span class="chip kw-op"><span>_OR_ / _AND_ を含む</span></span>
                        <span class="chip kw-bin"><span>utfbin_ を含む</span></span>
                    </div>
                </div>
            </details>

            <div class="group" data-kgroup="strongest">
                <div class="group__head">
                    <span class="group__title">strongest</span>
                    <span class="group__desc">最優先。name/desc 双方を見る。nameKeywords も指定可。</span>
                </div>
                <div class="group__hint">ドラッグ、または順位の数字を打ち替えて入れ替え</div>
                <button class="add-row add-top" data-add-top="strongest">先頭に追加</button>
                <div class="rows" data-sortable="strongest"></div>
                <button class="add-row" data-add="strongest">タグを追加</button>
            </div>

            <div class="group" data-kgroup="beforeCategory">
                <div class="group__head">
                    <span class="group__title">beforeCategory</span>
                    <span class="group__desc">カテゴリ番号ごとの優先タグ。カテゴリ内で並べ替え。</span>
                </div>
                <div id="beforeCategory-cats"></div>
            </div>

            <div class="group" data-kgroup="nameStrong">
                <div class="group__head">
                    <span class="group__title">nameStrong</span>
                    <span class="group__desc">部屋名にマッチさせる優先タグ。</span>
                </div>
                <div class="group__hint">ドラッグ、または順位の数字を打ち替えて入れ替え</div>
                <button class="add-row add-top" data-add-top="nameStrong">先頭に追加</button>
                <div class="rows" data-sortable="nameStrong"></div>
                <button class="add-row" data-add="nameStrong">タグを追加</button>
            </div>

            <div class="group" data-kgroup="descStrong">
                <div class="group__head">
                    <span class="group__title">descStrong</span>
                    <span class="group__desc">説明文にマッチさせる優先タグ。</span>
                </div>
                <div class="group__hint">ドラッグ、または順位の数字を打ち替えて入れ替え</div>
                <button class="add-row add-top" data-add-top="descStrong">先頭に追加</button>
                <div class="rows" data-sortable="descStrong"></div>
                <button class="add-row" data-add="descStrong">タグを追加</button>
            </div>

            <div class="group" data-kgroup="afterDescStrong">
                <div class="group__head">
                    <span class="group__title">afterDescStrong</span>
                    <span class="group__desc">説明文判定の後段で適用する優先タグ。</span>
                </div>
                <div class="group__hint">ドラッグ、または順位の数字を打ち替えて入れ替え</div>
                <button class="add-row add-top" data-add-top="afterDescStrong">先頭に追加</button>
                <div class="rows" data-sortable="afterDescStrong"></div>
                <button class="add-row" data-add="afterDescStrong">タグを追加</button>
            </div>
        </section>

        <!-- ───────── 略称・説明 (omitPattern / descriptions) ───────── -->
        <section class="panel" data-panel="meta">
            <div class="section-head">
                <h2>ラベルの略称・説明</h2>
                <p>略称=omitPattern / 説明文=descriptions</p>
            </div>
            <div class="meta-grid" id="meta-list"></div>
            <button class="add-row" data-add-meta>ラベルのメタ情報を追加</button>
        </section>

        <!-- ───────── リダイレクト ───────── -->
        <section class="panel" data-panel="redirects">
            <div class="section-head">
                <h2>リダイレクト (redirects)</h2>
                <p>旧ラベル → 新ラベル。改名時は自動追記されます。</p>
            </div>
            <div class="meta-grid" id="redirect-list"></div>
            <button class="add-row" data-add-redirect>リダイレクトを追加</button>
        </section>

        <!-- ───────── フィルタ・並び順 ───────── -->
        <section class="panel" data-panel="filters">
            <div class="section-head">
                <h2>フィルタ・並び順</h2>
                <p>recommendPageTagFilter / topPageTagFilter / filteredTagSort をJSONで直接編集</p>
            </div>
            <p class="filter-desc" style="border:none; padding-left:0; margin:0 0 18px;">
                これらは仕様が固まっておらず、普段は空のままです。必要なときだけ JSON を直接編集してください。
            </p>
            <div class="group">
                <div class="group__head">
                    <span class="group__title">recommendPageTagFilter</span>
                    <span class="group__desc">テーマ別一覧の関連テーマ除外<span class="filter-empty">現在は空</span></span>
                </div>
                <p class="filter-desc">
                    ここに入れたタグは、各テーマページの「<b>関連テーマ</b>」リストに表示しない除外リストです。
                    ただし、下の <code>filteredTagSort</code> で個別のテーマに明示したタグは表示されます。
                </p>
                <textarea class="raw-area" id="raw-recommendPageTagFilter" spellcheck="false"></textarea>
            </div>
            <div class="group">
                <div class="group__head">
                    <span class="group__title">topPageTagFilter</span>
                    <span class="group__desc">トップページ除外<span class="filter-empty">現在は空</span></span>
                </div>
                <p class="filter-desc">
                    トップページのテーマ一覧から隠すタグです。<code>recommendPageTagFilter</code> と<b>合算</b>して使われます。
                </p>
                <textarea class="raw-area" id="raw-topPageTagFilter" spellcheck="false"></textarea>
            </div>
            <div class="group">
                <div class="group__head">
                    <span class="group__title">filteredTagSort</span>
                    <span class="group__desc">関連テーマの手動指定・並び順<span class="filter-empty">現在は空</span></span>
                </div>
                <p class="filter-desc">
                    「テーマ名 → 関連タグの配列」のマップです。指定したテーマページで関連テーマを<b>手動で選び・並べ替え</b>られます（除外リストより<b>優先</b>されます）。
                </p>
                <textarea class="raw-area" id="raw-filteredTagSort" spellcheck="false"></textarea>
            </div>
        </section>

        <!-- ───────── RAW JSON ───────── -->
        <section class="panel" data-panel="raw">
            <div class="section-head">
                <h2>RAW JSON（全体）</h2>
                <p>編集中の状態を反映。直接書き換えて「取り込む」で反映できます。</p>
            </div>
            <textarea class="raw-area" id="raw-full" spellcheck="false" style="min-height:420px"></textarea>
            <div style="display:flex; gap:10px; margin-top:12px;">
                <button class="btn-ghost" id="raw-refresh" type="button">現在の状態を表示</button>
                <button class="btn-primary" id="raw-apply" type="button">このJSONを取り込む</button>
            </div>
        </section>
    </main>

    <!-- 改名モーダル -->
    <div class="overlay" id="rename-overlay">
        <div class="modal">
            <h3>ラベルを改名</h3>
            <p class="sub">新ラベルに変更し、<b>redirects に「旧→新」を自動追記</b>します。既存ラベルや旧キーと衝突する場合は保存できません。</p>
            <div class="field">
                <label class="f">旧ラベル</label>
                <div class="rename-from" id="rename-from"></div>
            </div>
            <div class="field">
                <label class="f">新ラベル</label>
                <input class="modal-input" id="rename-to" type="text" autocomplete="off">
            </div>
            <div class="modal__warn" id="rename-warn"></div>
            <div class="modal__foot">
                <button class="btn-ghost" id="rename-cancel" type="button">キャンセル</button>
                <button class="btn-primary" id="rename-confirm" type="button">改名する</button>
            </div>
        </div>
    </div>

    <!-- 略称・説明の対象ラベル選択モーダル（一覧タブの「追加」から開く） -->
    <div class="overlay" id="meta-pick-overlay">
        <div class="modal">
            <h3>ラベルのメタ情報を追加</h3>
            <p class="sub">略称・説明をつける<b>対象ラベルを既存の一覧から選んでください</b>。存在しないラベルに付けても効果がないため、一致しないと追加できません。</p>
            <div class="field">
                <label class="f">対象ラベル</label>
                <input class="modal-input" id="meta-pick-input" type="text" list="meta-label-options" autocomplete="off" placeholder="入力して候補から選択">
                <datalist id="meta-label-options"></datalist>
            </div>
            <div class="modal__warn" id="meta-pick-warn"></div>
            <div class="modal__foot">
                <button class="btn-ghost" id="meta-pick-cancel" type="button">キャンセル</button>
                <button class="btn-primary" id="meta-pick-confirm" type="button">このラベルに追加</button>
            </div>
        </div>
    </div>

    <!-- メタ編集モーダル（行から開く） -->
    <div class="overlay" id="meta-overlay">
        <div class="modal">
            <h3>略称・説明を編集</h3>
            <p class="sub" id="meta-modal-label"></p>
            <div class="field">
                <label class="f">略称 (omitPattern)</label>
                <input class="modal-input" id="meta-omit" type="text" autocomplete="off" placeholder="省略名（未入力なら削除）">
            </div>
            <div class="field">
                <label class="f">説明文 (descriptions)</label>
                <textarea class="modal-input" id="meta-desc" placeholder="説明文（複数行可・未入力なら削除）"></textarea>
            </div>
            <div class="modal__foot">
                <button class="btn-ghost" id="meta-cancel" type="button">キャンセル</button>
                <button class="btn-primary" id="meta-confirm" type="button">適用</button>
            </div>
        </div>
    </div>

    <div class="toast-wrap" id="toasts"></div>

    <!-- 並べ替えライブラリ（CDN・バージョン固定）。読み込みに失敗しても順位の手打ち移動と他機能は動作する。 -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js" crossorigin="anonymous"></script>

    <script>
    (function () {
        "use strict";

        // ── 保存先 URL（サーバ生成・XSS安全） ──
        var SAVE_URL = <?php echo json_encode(url('admin/recommend-tags/save'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        // CSRFトークン（サーバ埋め込み）。保存時に X-CSRF-Token ヘッダで送る。
        var CSRF_TOKEN = <?php echo json_encode($csrfToken ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        // ── モデル（ja.json 全体）。キー順を保つため元データの順序をそのまま保持 ──
        var model = JSON.parse(document.getElementById('boot-data').textContent);

        // ── カテゴリ番号 → 名前（AppConfig 由来・サーバ生成）。番号だけでは意味不明なため見出しに名前を併記 ──
        var CATEGORY_NAMES = {};
        try { CATEGORY_NAMES = JSON.parse(document.getElementById('category-names').textContent) || {}; }
        catch (e) { CATEGORY_NAMES = {}; }

        // キーワード群グループ（順序＝優先度）
        var KEYWORD_GROUPS = ['strongest', 'beforeCategory', 'nameStrong', 'descStrong', 'afterDescStrong'];

        var dirty = false;

        // ── ユーティリティ ──
        function el(tag, cls, txt) {
            var e = document.createElement(tag);
            if (cls) e.className = cls;
            if (txt != null) e.textContent = txt;
            return e;
        }
        function classifyKeyword(kw) {
            if (kw.indexOf('utfbin_') !== -1) return 'kw-bin';
            if (kw.indexOf('_AND_') !== -1 || kw.indexOf('_OR_') !== -1) return 'kw-op';
            return '';
        }
        function markDirty() {
            dirty = true;
            var s = document.getElementById('status');
            s.className = 'topbar__status dirty';
            s.querySelector('.txt').textContent = '未保存の変更';
        }
        function markSaved() {
            dirty = false;
            var s = document.getElementById('status');
            s.className = 'topbar__status saved';
            s.querySelector('.txt').textContent = '保存しました';
        }

        // すべてのラベル（現行 tag）を収集 ── 改名/追加の重複チェック用
        function collectAllLabels(excludeRef) {
            var set = {};
            KEYWORD_GROUPS.forEach(function (g) {
                if (g === 'beforeCategory') {
                    var bc = model.beforeCategory || {};
                    Object.keys(bc).forEach(function (cat) {
                        (bc[cat] || []).forEach(function (e) {
                            if (e !== excludeRef && e.tag) set[e.tag] = true;
                        });
                    });
                } else {
                    (model[g] || []).forEach(function (e) {
                        if (e !== excludeRef && e.tag) set[e.tag] = true;
                    });
                }
            });
            return set;
        }

        // 現行の全ユニークラベルをソート済み配列で返す（略称・説明の対象選択用）。
        // 改名等でラベルが増減しても、呼ぶたびに最新の状態から作り直す。
        function uniqueLabelsSorted() {
            return Object.keys(collectAllLabels(null)).sort(function (a, b) {
                return a.localeCompare(b, 'ja');
            });
        }
        // <datalist> の中身を最新ラベルで埋め直す
        function refreshMetaLabelOptions() {
            var dl = document.getElementById('meta-label-options');
            if (!dl) return;
            dl.innerHTML = '';
            uniqueLabelsSorted().forEach(function (l) {
                var o = document.createElement('option');
                o.value = l;
                dl.appendChild(o);
            });
        }

        // ── トースト ──
        function toast(msg, kind) {
            var wrap = document.getElementById('toasts');
            var t = el('div', 'toast ' + (kind || 'ok'));
            var ic = el('span', 'ic', kind === 'err' ? '✕' : '✓');
            t.appendChild(ic);
            t.appendChild(el('span', null, msg));
            wrap.appendChild(t);
            setTimeout(function () {
                t.style.transition = 'opacity .3s, transform .3s';
                t.style.opacity = '0';
                t.style.transform = 'translateY(10px)';
                setTimeout(function () { t.remove(); }, 320);
            }, kind === 'err' ? 6000 : 3200);
        }

        /* =========================================================
         *  キーワード行のレンダリング
         * ========================================================= */
        function renderKeywordRow(entry, list, idx, withName) {
            var row = el('div', 'row');
            // 行 → モデルエントリ・所属配列の対応を保持（SortableJS の onEnd / 順位手打ちで参照する）
            row._entry = entry;
            row._list = list;
            if (model.omitPattern && model.omitPattern[entry.tag] != null ||
                model.descriptions && model.descriptions[entry.tag] != null) {
                row.classList.add('has-meta');
                var flag = el('span', 'meta-flag', '略称/説明あり');
                row.appendChild(flag);
            }

            // ハンドル（SortableJS の handle セレクタ対象）
            var handle = el('div', 'row__handle');
            handle.title = 'ドラッグして並べ替え';
            handle.innerHTML = '<svg viewBox="0 0 14 22" fill="currentColor"><circle cx="4" cy="4" r="1.6"/><circle cx="10" cy="4" r="1.6"/><circle cx="4" cy="11" r="1.6"/><circle cx="10" cy="11" r="1.6"/><circle cx="4" cy="18" r="1.6"/><circle cx="10" cy="18" r="1.6"/></svg>';
            row.appendChild(handle);

            // ランク（数値入力。打ち替えて Enter / blur でその位置へ移動）
            var rank = el('div', 'row__rank');
            var rankInput = el('input', 'n');
            rankInput.type = 'number';
            rankInput.min = '1';
            rankInput.step = '1';
            rankInput.value = String(idx + 1);
            rankInput.title = '順位を打ち替えて Enter で移動';
            rankInput.setAttribute('aria-label', '順位');
            row._rankInput = rankInput;
            // ドラッグ中に数字入力をつかまないよう、Sortable のドラッグ対象から除外
            rankInput.setAttribute('data-no-drag', '1');
            function commitRank() {
                var container = row.parentElement;
                if (!container) return;
                moveRowToPosition(container, row, rankInput.value);
            }
            rankInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); commitRank(); }
                else if (e.key === 'Escape') { e.preventDefault(); rankInput.value = String(rankOf(row)); rankInput.blur(); }
            });
            // フォーカスが外れたら確定（クリックで別行へ移った等）。再フォーカスはしない。
            rankInput.addEventListener('blur', commitRank);
            rank.appendChild(rankInput);
            rank.appendChild(el('span', 'unit', '位'));
            row.appendChild(rank);

            // 本体
            var body = el('div', 'row__body');

            var tagField = el('div', 'field');
            tagField.appendChild(el('label', 'field__label', 'タグ（ラベル）'));
            var tagInput = el('input', 'tag-input');
            tagInput.type = 'text';
            tagInput.value = entry.tag || '';
            tagInput.placeholder = 'タグ名';
            tagInput.addEventListener('input', function () { entry.tag = tagInput.value; markDirty(); });
            tagField.appendChild(tagInput);
            body.appendChild(tagField);

            // keywords チップ
            var kwField = el('div', 'field');
            var kwLabel = el('label', 'field__label', 'キーワード');
            var optHint = el('span', 'opt', '');
            kwLabel.appendChild(optHint);
            kwField.appendChild(kwLabel);
            var kwBox = buildChips(entry, 'keywords', function () { refreshSelfHint(entry, optHint); });
            kwField.appendChild(kwBox);
            // 省略時の挙動説明
            refreshSelfHint(entry, optHint);
            body.appendChild(kwField);

            // nameKeywords（strongest のみ）
            if (withName) {
                var nkField = el('div', 'field');
                nkField.appendChild(el('label', 'field__label', 'nameKeywords（任意・strongest限定）'));
                var nkBox = buildChips(entry, 'nameKeywords', null);
                nkField.appendChild(nkBox);
                body.appendChild(nkField);
            }
            row.appendChild(body);

            // アクション（本体最下段の小さな横並びボタン群）
            var actions = el('div', 'row__actions');
            function actionBtn(cls, icon, label) {
                var b = el('button', 'icon-btn ' + cls);
                b.type = 'button';
                b.appendChild(el('span', 'ic', icon));
                b.appendChild(el('span', null, label));
                return b;
            }
            var renameBtn = actionBtn('rename', '✎', '改名');
            renameBtn.addEventListener('click', function () { openRename(entry, tagInput); });
            var metaBtn = actionBtn('meta', '◷', '略称・説明');
            metaBtn.addEventListener('click', function () { openMeta(entry.tag, row); });
            var delBtn = actionBtn('del', '🗑', '削除');
            delBtn.addEventListener('click', function () {
                if (!confirm('「' + (entry.tag || '(空)') + '」を削除しますか？')) return;
                var i = list.indexOf(entry);
                if (i !== -1) list.splice(i, 1);
                markDirty();
                var container = row.parentElement;
                row.remove();
                if (container) renumber(container);
            });
            actions.appendChild(renameBtn);
            actions.appendChild(metaBtn);
            actions.appendChild(delBtn);
            body.appendChild(actions);

            return row;
        }

        // keywords 省略 = tag 自身がキーワード、を表示
        function refreshSelfHint(entry, optHint) {
            if (!Array.isArray(entry.keywords) || entry.keywords.length === 0) {
                optHint.textContent = '（省略中＝タグ名自身がキーワード）';
            } else {
                optHint.textContent = '';
            }
        }

        /* チップ式の文字列配列エディタ。entry[key] が配列でなければ空配列扱い。
         * 値は input の値をそのまま保持（日本語・絵文字・_AND_ 等を壊さない）。 */
        function buildChips(entry, key, onChange) {
            var box = el('div', 'chips');

            function ensureArr() {
                if (!Array.isArray(entry[key])) entry[key] = [];
                return entry[key];
            }

            function renderChip(val, i) {
                var chip = el('div', 'chip ' + classifyKeyword(val));
                var span = el('span', null, val);
                span.title = val;
                chip.appendChild(span);
                var x = el('button', 'chip__x', '×');
                x.type = 'button';
                x.addEventListener('click', function () {
                    var arr = ensureArr();
                    arr.splice(i, 1);
                    if (arr.length === 0) delete entry[key]; // 空配列ならキー自体を削除（=省略）
                    markDirty();
                    redraw();
                    if (onChange) onChange();
                });
                chip.appendChild(x);
                return chip;
            }

            var input = el('input', 'chips__input');
            input.type = 'text';
            input.placeholder = (key === 'keywords') ? 'キーワードを入力しEnter' : '追加してEnter';
            input.spellcheck = false;

            function commit() {
                var v = input.value;
                if (v === '') return;
                var arr = ensureArr();
                arr.push(v);
                input.value = '';
                markDirty();
                redraw();
                if (onChange) onChange();
            }
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); commit(); }
                else if (e.key === 'Backspace' && input.value === '') {
                    var arr = ensureArr();
                    if (arr.length) {
                        arr.pop();
                        if (arr.length === 0) delete entry[key];
                        markDirty(); redraw(); if (onChange) onChange();
                    }
                }
            });
            input.addEventListener('blur', commit);

            function redraw() {
                box.innerHTML = '';
                var arr = Array.isArray(entry[key]) ? entry[key] : [];
                arr.forEach(function (v, i) { box.appendChild(renderChip(v, i)); });
                box.appendChild(input);
            }
            redraw();
            return box;
        }

        /* =========================================================
         *  並べ替え（SortableJS・同一リスト内のみ。CDN未読込でも順位手打ちは動く）
         * =========================================================
         *  各リスト要素は固有の group 名を持たせる（共有しない）ため、リスト間の
         *  ドロップは発生しない＝現状どおり「同一リスト内のみ並べ替え」を維持する。
         */
        function applySortable(container, list) {
            if (!container) return;
            // 既存インスタンスがあれば破棄（再描画で要素を作り直すケースに備える）
            if (container._sortable && typeof container._sortable.destroy === 'function') {
                try { container._sortable.destroy(); } catch (e) {}
                container._sortable = null;
            }
            container._list = list; // onEnd 等から参照
            if (typeof window.Sortable === 'undefined') return; // CDN失敗時は何もしない（手打ち移動は別系統で機能）

            container._sortable = window.Sortable.create(container, {
                handle: '.row__handle',
                // 順位の数値入力をドラッグ起点にしない（クリックして打ち替えできるように）
                filter: '[data-no-drag]',
                preventOnFilter: false,
                animation: 150,
                forceFallback: true,      // ネイティブDnDを使わず自前ドラッグ＝全ブラウザで挙動統一＆オートスクロール確実化
                fallbackOnBody: true,
                scroll: true,
                scrollSensitivity: 80,
                scrollSpeed: 14,
                bubbleScroll: true,
                // group を共有しない（固有名）ので他リストへはドロップ不可
                group: 'tag-list-' + (container.getAttribute('data-sortable') || Math.random().toString(36).slice(2)),
                onEnd: function (evt) {
                    var c = evt.to; // = evt.from（同一リスト内のみ）
                    reorderModelFromDom(c, c._list);
                    renumber(c);
                    markDirty();
                }
            });
        }

        // DOM 上の行順に合わせて list（モデル配列）を in-place で並べ替える
        function reorderModelFromDom(container, list) {
            if (!Array.isArray(list)) return;
            var newOrder = [];
            Array.prototype.forEach.call(container.children, function (c) {
                if (c._entry) newOrder.push(c._entry);
            });
            list.length = 0;
            newOrder.forEach(function (en) { list.push(en); });
        }
        // 順位バッジを 1..n に振り直す（数値入力の value を更新）
        function renumber(container) {
            var n = 0;
            Array.prototype.forEach.call(container.children, function (c) {
                var r = (c.querySelector) ? c.querySelector('.row__rank .n') : null;
                if (r) { n++; r.value = String(n); }
            });
        }

        // 行 row の現在の 1始まり順位を返す（Escape 取り消し時の復元に使う）
        function rankOf(row) {
            var container = row.parentElement;
            if (!container) return 1;
            var rows = Array.prototype.filter.call(container.children, function (c) { return c.classList && c.classList.contains('row'); });
            var i = rows.indexOf(row);
            return (i === -1) ? 1 : i + 1;
        }

        // 順位の手打ち移動: row を同一リスト内の 1始まり pos 番目へ移動（範囲外は 1..件数 にクランプ）
        // SortableJS 非依存で動作する（DOM を直接動かしてモデルを再構築）。
        function moveRowToPosition(container, row, rawValue) {
            var rows = Array.prototype.filter.call(container.children, function (c) { return c.classList && c.classList.contains('row'); });
            var total = rows.length;
            var cur = rows.indexOf(row);
            if (cur === -1) return;
            var target = parseInt(rawValue, 10);
            if (isNaN(target)) { renumber(container); return; } // 不正入力は現状へ戻す
            if (target < 1) target = 1;
            if (target > total) target = total;
            var targetIdx = target - 1;
            if (targetIdx === cur) { renumber(container); return; } // 変化なし

            // フォーカスが入力にあるか（Enter 確定なら移動後も入力に残す）
            var keepFocus = (row._rankInput && document.activeElement === row._rankInput);

            // targetIdx 番目（自分を除いた並び）へ挿入
            var without = rows.slice();
            without.splice(cur, 1);
            var refNode = without[targetIdx] || null;
            container.insertBefore(row, refNode);

            reorderModelFromDom(container, container._list);
            renumber(container);
            markDirty();
            if (keepFocus && row._rankInput) { try { row._rankInput.focus(); row._rankInput.select(); } catch (e) {} }
        }

        /* =========================================================
         *  各グループの描画
         * ========================================================= */
        function renderList(container, list, withName) {
            if (!container) return;
            container.innerHTML = '';
            container._list = list;
            if (Array.isArray(list)) {
                list.forEach(function (entry, i) {
                    container.appendChild(renderKeywordRow(entry, list, i, withName));
                });
            }
            // 並べ替えを（再）適用。要素を作り直したので既存インスタンスは破棄→再生成される。
            applySortable(container, list);
        }

        function renderBeforeCategory() {
            var host = document.getElementById('beforeCategory-cats');
            host.innerHTML = '';
            var bc = model.beforeCategory || (model.beforeCategory = {});
            var cats = Object.keys(bc);
            if (cats.length === 0) {
                host.appendChild(el('div', 'empty', 'カテゴリがありません。'));
            }
            cats.forEach(function (cat) {
                var wrap = el('div', 'subcat');
                var bar = el('div', 'subcat__bar');
                var badge = el('span', 'badge', 'カテゴリ ' + cat);
                bar.appendChild(badge);
                var catNm = CATEGORY_NAMES[String(cat)];
                if (catNm) bar.appendChild(el('span', 'catname', catNm));
                bar.appendChild(el('span', 'nm', (bc[cat] || []).length + ' 件'));
                wrap.appendChild(bar);

                var rows = el('div', 'rows beforecat');
                rows.setAttribute('data-sortable', 'beforeCategory:' + cat);
                renderList(rows, bc[cat], false);

                var addTop = el('button', 'add-row add-top', '先頭に追加');
                addTop.type = 'button';
                addTop.addEventListener('click', function () {
                    if (!Array.isArray(bc[cat])) bc[cat] = [];
                    bc[cat].unshift({ tag: '' });
                    renderList(rows, bc[cat], false);
                    markDirty();
                    var input = rows.firstElementChild && rows.firstElementChild.querySelector('.tag-input');
                    if (input) input.focus();
                });
                wrap.appendChild(addTop);
                wrap.appendChild(rows);

                var add = el('button', 'add-row', 'このカテゴリにタグを追加');
                add.type = 'button';
                add.addEventListener('click', function () {
                    if (!Array.isArray(bc[cat])) bc[cat] = [];
                    var entry = { tag: '' };
                    bc[cat].push(entry);
                    rows._list = bc[cat];
                    rows.appendChild(renderKeywordRow(entry, bc[cat], bc[cat].length - 1, false));
                    if (!rows._sortable) applySortable(rows, bc[cat]);
                    renumber(rows);
                    markDirty();
                });
                wrap.appendChild(add);
                host.appendChild(wrap);
            });
        }

        function renderAllKeywordGroups() {
            renderList(document.querySelector('[data-sortable="strongest"]'), model.strongest, true);
            renderBeforeCategory();
            renderList(document.querySelector('[data-sortable="nameStrong"]'), model.nameStrong, false);
            renderList(document.querySelector('[data-sortable="descStrong"]'), model.descStrong, false);
            renderList(document.querySelector('[data-sortable="afterDescStrong"]'), model.afterDescStrong, false);
        }

        // 追加ボタン（トップレベルのリスト群）
        document.querySelectorAll('[data-add]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var g = btn.getAttribute('data-add');
                if (!Array.isArray(model[g])) model[g] = [];
                var entry = { tag: '' };
                model[g].push(entry);
                var container = document.querySelector('[data-sortable="' + g + '"]');
                container._list = model[g]; // 配列を作り直した場合に備えて参照を更新
                container.appendChild(renderKeywordRow(entry, model[g], model[g].length - 1, g === 'strongest'));
                if (!container._sortable) applySortable(container, model[g]); // 初回が空だった場合に備えて適用
                renumber(container);
                markDirty();
                var input = container.lastElementChild.querySelector('.tag-input');
                if (input) input.focus();
            });
        });

        // 追加ボタン（先頭へ挿入・トップレベルのリスト群）
        document.querySelectorAll('[data-add-top]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var g = btn.getAttribute('data-add-top');
                if (!Array.isArray(model[g])) model[g] = [];
                model[g].unshift({ tag: '' });
                var container = document.querySelector('[data-sortable="' + g + '"]');
                renderList(container, model[g], g === 'strongest');
                markDirty();
                var input = container.firstElementChild && container.firstElementChild.querySelector('.tag-input');
                if (input) input.focus();
            });
        });

        /* =========================================================
         *  略称・説明（omitPattern / descriptions）
         * ========================================================= */
        function renderMetaList() {
            var host = document.getElementById('meta-list');
            host.innerHTML = '';
            model.omitPattern = model.omitPattern || {};
            model.descriptions = model.descriptions || {};
            // 両方のキーを統合（順序: omitPattern → descriptions の新規）
            var labels = [];
            var seen = {};
            Object.keys(model.omitPattern).forEach(function (l) { if (!seen[l]) { seen[l] = 1; labels.push(l); } });
            Object.keys(model.descriptions).forEach(function (l) { if (!seen[l]) { seen[l] = 1; labels.push(l); } });

            if (labels.length === 0) host.appendChild(el('div', 'empty', '略称・説明はまだありません。'));

            labels.forEach(function (label) { host.appendChild(metaCard(label)); });
        }
        function metaCard(label) {
            var card = el('div', 'meta-card three');

            var c1 = el('div');
            c1.appendChild(el('div', 'lbl', 'ラベル'));
            var labelInput = el('input', 'meta-input');
            labelInput.type = 'text';
            labelInput.value = label;
            labelInput.readOnly = true;
            labelInput.style.fontWeight = '700';
            c1.appendChild(labelInput);
            var omitWrap = el('div');
            omitWrap.style.marginTop = '8px';
            omitWrap.appendChild(el('div', 'lbl', '略称 (omitPattern)'));
            var omit = el('input', 'meta-input');
            omit.type = 'text';
            omit.placeholder = '（なし）';
            omit.value = model.omitPattern[label] != null ? model.omitPattern[label] : '';
            omit.addEventListener('input', function () {
                if (omit.value === '') delete model.omitPattern[label];
                else model.omitPattern[label] = omit.value;
                markDirty();
            });
            omitWrap.appendChild(omit);
            c1.appendChild(omitWrap);
            card.appendChild(c1);

            var c2 = el('div');
            c2.appendChild(el('div', 'lbl', '説明文 (descriptions)'));
            var desc = el('textarea', 'meta-area');
            desc.value = model.descriptions[label] != null ? model.descriptions[label] : '';
            desc.placeholder = '（なし）';
            desc.addEventListener('input', function () {
                if (desc.value === '') delete model.descriptions[label];
                else model.descriptions[label] = desc.value;
                markDirty();
            });
            c2.appendChild(desc);
            card.appendChild(c2);

            var c3 = el('div');
            var del = el('button', 'icon-btn del', '🗑');
            del.type = 'button';
            del.title = 'このラベルの略称・説明を削除';
            del.addEventListener('click', function () {
                delete model.omitPattern[label];
                delete model.descriptions[label];
                markDirty();
                card.remove();
            });
            c3.appendChild(del);
            card.appendChild(c3);
            return card;
        }
        // 対象ラベルは「既存のユニークラベルから選ぶ」。開くたびに最新の一覧を再生成する。
        function openMetaPick() {
            refreshMetaLabelOptions();
            var inp = document.getElementById('meta-pick-input');
            inp.value = '';
            document.getElementById('meta-pick-warn').className = 'modal__warn';
            document.getElementById('meta-pick-overlay').classList.add('open');
            setTimeout(function () { inp.focus(); }, 30);
        }
        function closeMetaPick() {
            document.getElementById('meta-pick-overlay').classList.remove('open');
        }
        function confirmMetaPick() {
            var inp = document.getElementById('meta-pick-input');
            var warn = document.getElementById('meta-pick-warn');
            var label = (inp.value || '').trim();
            if (label === '') { warn.textContent = 'ラベルを選択してください。'; warn.classList.add('show'); return; }
            // 手打ちも許容するが、既存ラベルに一致するときだけ確定できる
            if (!collectAllLabels(null)[label]) {
                warn.textContent = '「' + label + '」は存在するタグラベルではありません。一覧の候補から選んでください。';
                warn.classList.add('show');
                return;
            }
            model.omitPattern = model.omitPattern || {};
            model.descriptions = model.descriptions || {};
            if (model.omitPattern[label] == null && model.descriptions[label] == null) {
                model.omitPattern[label] = '';
            }
            renderMetaList();
            markDirty();
            closeMetaPick();
            toast('「' + label + '」の略称・説明を編集できます');
        }
        document.querySelector('[data-add-meta]').addEventListener('click', openMetaPick);
        document.getElementById('meta-pick-cancel').addEventListener('click', closeMetaPick);
        document.getElementById('meta-pick-confirm').addEventListener('click', confirmMetaPick);
        document.getElementById('meta-pick-input').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); confirmMetaPick(); }
        });

        /* =========================================================
         *  redirects
         * ========================================================= */
        function renderRedirects() {
            var host = document.getElementById('redirect-list');
            host.innerHTML = '';
            model.redirects = model.redirects || {};
            var keys = Object.keys(model.redirects);
            if (keys.length === 0) host.appendChild(el('div', 'empty', 'リダイレクトはありません。'));
            keys.forEach(function (oldLabel) { host.appendChild(redirectCard(oldLabel)); });
        }
        function redirectCard(oldLabel) {
            var card = el('div', 'meta-card three');

            var c1 = el('div');
            c1.appendChild(el('div', 'lbl', '旧ラベル'));
            var oldIn = el('input', 'meta-input mono');
            oldIn.type = 'text';
            oldIn.value = oldLabel;
            // 旧ラベル(キー)の変更: キーを差し替え（順序維持のため再構築）
            oldIn.addEventListener('change', function () {
                var nv = oldIn.value;
                if (nv === oldLabel) return;
                if (nv === '') { toast('旧ラベルは空にできません', 'err'); oldIn.value = oldLabel; return; }
                if (model.redirects[nv] != null) { toast('その旧ラベルは既に存在します', 'err'); oldIn.value = oldLabel; return; }
                var rebuilt = {};
                Object.keys(model.redirects).forEach(function (k) {
                    rebuilt[k === oldLabel ? nv : k] = model.redirects[k];
                });
                model.redirects = rebuilt;
                markDirty();
                renderRedirects();
            });
            c1.appendChild(oldIn);
            card.appendChild(c1);

            var c2 = el('div');
            c2.appendChild(el('div', 'lbl', '新ラベル（転送先）'));
            var newIn = el('input', 'meta-input');
            newIn.type = 'text';
            newIn.value = model.redirects[oldLabel];
            newIn.addEventListener('input', function () { model.redirects[oldLabel] = newIn.value; markDirty(); });
            c2.appendChild(newIn);
            card.appendChild(c2);

            var c3 = el('div');
            var del = el('button', 'icon-btn del', '🗑');
            del.type = 'button';
            del.addEventListener('click', function () {
                delete model.redirects[oldLabel];
                markDirty();
                card.remove();
            });
            c3.appendChild(del);
            card.appendChild(c3);
            return card;
        }
        document.querySelector('[data-add-redirect]').addEventListener('click', function () {
            model.redirects = model.redirects || {};
            var key = '新しい旧ラベル';
            var i = 1;
            while (model.redirects[key] != null) { key = '新しい旧ラベル' + (++i); }
            model.redirects[key] = '';
            renderRedirects();
            markDirty();
        });

        /* =========================================================
         *  ラベル改名（モデル更新 + redirects 自動追記 + 重複チェック）
         * ========================================================= */
        var renameCtx = null;
        function openRename(entry, tagInput) {
            renameCtx = { entry: entry, tagInput: tagInput };
            document.getElementById('rename-from').textContent = entry.tag || '(空)';
            var to = document.getElementById('rename-to');
            to.value = entry.tag || '';
            document.getElementById('rename-warn').className = 'modal__warn';
            document.getElementById('rename-overlay').classList.add('open');
            setTimeout(function () { to.focus(); to.select(); }, 30);
        }
        function closeRename() {
            document.getElementById('rename-overlay').classList.remove('open');
            renameCtx = null;
        }
        // 重複禁止チェック: 新ラベルが「他の現行ラベル」「redirects の旧キー」と衝突しないこと
        function renameConflict(newLabel, entry) {
            if (newLabel === '') return '新ラベルが空です。';
            if (newLabel === entry.tag) return 'ラベルが変わっていません。';
            var labels = collectAllLabels(entry); // 自分以外の現行ラベル
            if (labels[newLabel]) return '新ラベル「' + newLabel + '」は既に他のタグで使われています。';
            var redirects = model.redirects || {};
            if (redirects[newLabel] != null) return '新ラベル「' + newLabel + '」は redirects の旧キーと衝突します。';
            return null;
        }
        document.getElementById('rename-confirm').addEventListener('click', function () {
            if (!renameCtx) return;
            var newLabel = document.getElementById('rename-to').value;
            var entry = renameCtx.entry;
            var err = renameConflict(newLabel, entry);
            if (err) {
                var w = document.getElementById('rename-warn');
                w.textContent = err;
                w.classList.add('show');
                return;
            }
            var oldLabel = entry.tag;
            // (a) モデル内ラベル更新
            entry.tag = newLabel;
            if (renameCtx.tagInput) renameCtx.tagInput.value = newLabel;
            // (b) redirects に 旧→新 を自動追記（旧ラベルが非空のときのみ）
            if (oldLabel && oldLabel !== newLabel) {
                model.redirects = model.redirects || {};
                model.redirects[oldLabel] = newLabel;
            }
            // 略称・説明のキーも追従させる（あれば）
            if (model.omitPattern && model.omitPattern[oldLabel] != null) {
                model.omitPattern[newLabel] = model.omitPattern[oldLabel];
                delete model.omitPattern[oldLabel];
            }
            if (model.descriptions && model.descriptions[oldLabel] != null) {
                model.descriptions[newLabel] = model.descriptions[oldLabel];
                delete model.descriptions[oldLabel];
            }
            markDirty();
            closeRename();
            // メタ/リダイレクト一覧は最新化、キーワード行は入力欄に反映済み
            renderMetaList();
            renderRedirects();
            toast('「' + oldLabel + '」→「' + newLabel + '」に改名し、リダイレクトを追記しました');
        });
        document.getElementById('rename-cancel').addEventListener('click', closeRename);

        /* =========================================================
         *  行から略称・説明モーダル
         * ========================================================= */
        var metaCtx = null;
        function openMeta(label, row) {
            if (!label) { toast('先にタグ名を入力してください', 'err'); return; }
            metaCtx = { label: label, row: row };
            document.getElementById('meta-modal-label').textContent = 'ラベル: ' + label;
            model.omitPattern = model.omitPattern || {};
            model.descriptions = model.descriptions || {};
            document.getElementById('meta-omit').value = model.omitPattern[label] != null ? model.omitPattern[label] : '';
            document.getElementById('meta-desc').value = model.descriptions[label] != null ? model.descriptions[label] : '';
            document.getElementById('meta-overlay').classList.add('open');
        }
        function closeMeta() { document.getElementById('meta-overlay').classList.remove('open'); metaCtx = null; }
        document.getElementById('meta-confirm').addEventListener('click', function () {
            if (!metaCtx) return;
            var label = metaCtx.label;
            var omit = document.getElementById('meta-omit').value;
            var desc = document.getElementById('meta-desc').value;
            if (omit === '') delete model.omitPattern[label]; else model.omitPattern[label] = omit;
            if (desc === '') delete model.descriptions[label]; else model.descriptions[label] = desc;
            markDirty();
            // 行のメタフラグ更新
            var row = metaCtx.row;
            var existing = row.querySelector('.meta-flag');
            var hasMeta = (model.omitPattern[label] != null) || (model.descriptions[label] != null);
            if (hasMeta && !existing) {
                row.classList.add('has-meta');
                row.insertBefore((function () { var f = el('span', 'meta-flag', '略称/説明あり'); return f; })(), row.firstChild);
            } else if (!hasMeta && existing) {
                existing.remove();
                row.classList.remove('has-meta');
            }
            renderMetaList();
            closeMeta();
            toast('略称・説明を更新しました');
        });
        document.getElementById('meta-cancel').addEventListener('click', closeMeta);

        // オーバーレイ背景クリックで閉じる
        document.querySelectorAll('.overlay').forEach(function (ov) {
            ov.addEventListener('click', function (e) { if (e.target === ov) ov.classList.remove('open'); });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') document.querySelectorAll('.overlay.open').forEach(function (o) { o.classList.remove('open'); });
        });

        /* =========================================================
         *  フィルタ・並び順（textarea で JSON 直接編集）
         * ========================================================= */
        var FILTER_KEYS = ['recommendPageTagFilter', 'topPageTagFilter', 'filteredTagSort'];
        function loadFilterAreas() {
            FILTER_KEYS.forEach(function (k) {
                var ta = document.getElementById('raw-' + k);
                var val = (model[k] !== undefined) ? model[k] : null;
                ta.value = JSON.stringify(val, null, 4);
            });
        }
        function commitFilterAreas() {
            // 保存直前にフィルタ群の textarea をモデルへ反映。エラーがあれば返す。
            for (var i = 0; i < FILTER_KEYS.length; i++) {
                var k = FILTER_KEYS[i];
                var ta = document.getElementById('raw-' + k);
                var txt = ta.value.trim();
                if (txt === '' || txt === 'null') { delete model[k]; continue; }
                try {
                    model[k] = JSON.parse(txt);
                } catch (e) {
                    return k + ' のJSONが不正です: ' + e.message;
                }
            }
            return null;
        }
        FILTER_KEYS.forEach(function (k) {
            document.getElementById('raw-' + k).addEventListener('input', markDirty);
        });

        /* =========================================================
         *  RAW JSON タブ
         * ========================================================= */
        function buildPayload() {
            // フィルタ textarea を反映してから全体を返す
            var err = commitFilterAreas();
            if (err) throw new Error(err);
            return model;
        }
        function refreshRaw() {
            try {
                var p = buildPayload();
                document.getElementById('raw-full').value = JSON.stringify(p, null, 4);
            } catch (e) {
                toast(e.message, 'err');
            }
        }
        document.getElementById('raw-refresh').addEventListener('click', refreshRaw);
        document.getElementById('raw-apply').addEventListener('click', function () {
            var txt = document.getElementById('raw-full').value;
            var parsed;
            try { parsed = JSON.parse(txt); }
            catch (e) { toast('JSONが不正です: ' + e.message, 'err'); return; }
            if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
                toast('トップレベルはオブジェクトである必要があります', 'err'); return;
            }
            model = parsed;
            renderAllKeywordGroups();
            renderMetaList();
            renderRedirects();
            loadFilterAreas();
            refreshTabCounts();
            markDirty();
            // 取り込み結果をすぐ確認できるよう編集画面（キーワード群）へ自動で切り替える
            var kwTab = document.querySelector('.tab[data-tab="keywords"]');
            if (kwTab) kwTab.click();
            toast('RAW JSON を取り込みました（編集画面に反映）');
        });

        /* =========================================================
         *  タブ切り替え
         * ========================================================= */
        document.getElementById('tabs').addEventListener('click', function (e) {
            var btn = e.target.closest('.tab');
            if (!btn) return;
            var name = btn.getAttribute('data-tab');
            document.querySelectorAll('.tab').forEach(function (t) { t.classList.toggle('active', t === btn); });
            document.querySelectorAll('.panel').forEach(function (p) {
                p.classList.toggle('active', p.getAttribute('data-panel') === name);
            });
            if (name === 'raw') refreshRaw();
            if (name === 'filters') loadFilterAreas();
        });

        // タブのカウント表示
        function refreshTabCounts() {
            // キーワード群: strongest+beforeCategory全件+name/desc/afterDesc
            var kw = (model.strongest || []).length + (model.nameStrong || []).length +
                     (model.descStrong || []).length + (model.afterDescStrong || []).length;
            var bc = model.beforeCategory || {};
            Object.keys(bc).forEach(function (c) { kw += (bc[c] || []).length; });
            setCount('keywords', kw);
            var metaN = Object.keys(model.omitPattern || {}).length;
            Object.keys(model.descriptions || {}).forEach(function (l) {
                if (!(model.omitPattern && model.omitPattern[l] != null)) metaN++;
            });
            setCount('meta', metaN);
            setCount('redirects', Object.keys(model.redirects || {}).length);
        }
        function setCount(tab, n) {
            var btn = document.querySelector('.tab[data-tab="' + tab + '"]');
            if (!btn) return;
            var c = btn.querySelector('.count');
            if (!c) { c = el('span', 'count'); btn.appendChild(c); }
            c.textContent = n;
        }

        /* =========================================================
         *  保存
         * ========================================================= */
        // 保存前の最終重複チェック（現行ラベル同士・redirects旧キーとの衝突）
        function preflightDuplicateCheck() {
            var seen = {};
            // 同名ラベルが複数グループ/カテゴリに跨って存在するのは正常仕様
            // （例: SEVENTEEN は beforeCategory の 26 と 33 の両方に入る）。重複自体はエラーにしない。
            function check(list) {
                (list || []).forEach(function (e) {
                    if (!e.tag) return;
                    seen[e.tag] = true;
                });
            }
            check(model.strongest); check(model.nameStrong); check(model.descStrong); check(model.afterDescStrong);
            var bc = model.beforeCategory || {};
            Object.keys(bc).forEach(function (c) { check(bc[c]); });

            // 現行ラベルが redirects の旧キーに含まれていないか（改名後に旧名で復活＝循環の元）
            var redirects = model.redirects || {};
            var conflict = null;
            Object.keys(seen).forEach(function (label) {
                if (redirects[label] != null) conflict = label;
            });
            if (conflict) return '現行ラベル「' + conflict + '」が redirects の旧ラベルと衝突しています。';
            return null;
        }

        var saveBtn = document.getElementById('btn-save');
        function doSave() {
            // 1) フィルタ textarea を反映
            var ferr = commitFilterAreas();
            if (ferr) { toast(ferr, 'err'); return; }
            // 2) 重複チェック
            var derr = preflightDuplicateCheck();
            if (derr) { toast(derr, 'err'); return; }

            saveBtn.disabled = true;
            var orig = saveBtn.innerHTML;
            saveBtn.textContent = '保存中…';

            fetch(SAVE_URL, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify(model)
            }).then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            }).then(function (r) {
                if (r.data && r.data.ok) {
                    markSaved();
                    toast('保存しました。git commit で変更を残してください。');
                } else {
                    var msg = (r.data && r.data.error) ? r.data.error : '保存に失敗しました。';
                    toast(msg, 'err');
                }
            }).catch(function (e) {
                toast('通信に失敗しました: ' + e.message, 'err');
            }).finally(function () {
                saveBtn.disabled = false;
                saveBtn.innerHTML = orig;
            });
        }
        saveBtn.addEventListener('click', doSave);

        // ⌘S / Ctrl+S で保存
        document.addEventListener('keydown', function (e) {
            if ((e.metaKey || e.ctrlKey) && (e.key === 's' || e.key === 'S')) {
                e.preventDefault();
                doSave();
            }
        });

        // 離脱警告
        window.addEventListener('beforeunload', function (e) {
            if (dirty) { e.preventDefault(); e.returnValue = ''; }
        });

        /* ── 初期描画 ── */
        renderAllKeywordGroups();
        renderMetaList();
        renderRedirects();
        loadFilterAreas();
        refreshTabCounts();
        // モデルが変わるたびカウント更新（軽量なので保存系イベントで都度）
        document.addEventListener('input', function () { refreshTabCounts(); });
        document.addEventListener('click', function () { setTimeout(refreshTabCounts, 0); });
    })();
    </script>
</body>

</html>
