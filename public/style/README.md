# public/style の構成

```
tokens.css    … 全色のデザイントークン定義（サイト内で生の色リテラルを書いてよい唯一の場所）
base/         … サイト骨格
                mvp.css    = MVP.cssフレームワーク（カスタマイズ済）
                mvpmin.css = 軽量版（検索・ポリシー・エラー系ヘッダ用）
                unset.css  = .unset（all: unset の打ち消し一式）
components/   … 2ページ以上で使う部品CSS（ヘッダ・フッタ・一覧アイテム・検索フォーム等）
pages/        … 特定ページ（ファミリー）専用CSS（ルーム詳細・recommend・ブログ等）
react/        … React コンポーネント用の手書きCSS（Viteビルド成果物ではない）
```

## ルール

- **色は必ず `tokens.css` の `--c-*`（セマンティックトークン）を参照する。**
  生の色リテラル（`#hex` / `rgb()` / data-URI 内の `%23xxxxxx`）を書いてよいのは
  `tokens.css` だけ。検査: `make css-check`
- **読み込み順**: tokens.css → base/(mvp|mvpmin).css → base/unset.css → ページ別CSS。
  この順序はヘッダテンプレート（`app/Views/components/head.php` 等）が保証する。
  mvp系を直接 `<link>` するViewは必ず tokens.css も読み込むこと（css-checkが検査）。
- **配置規則**: 2ページ以上で使う部品 → `components/`、
  特定ページやその一族（例: ルーム詳細＋入室確認）専用 → `pages/`。
- **`$_css` の指定**: コントローラでサブパス込みで指定する。
  例: `$_css = ['components/room_list', 'components/site_header', 'pages/room_page'];`
- admin/* の画面は独自デザインシステムのためトークン化の対象外
  （ただし base/ や components/ のCSSを読む場合は tokens.css も必要）。

## ダークモード（導入予定）

トークンのダーク値（Slate系）は `tokens.css` の `[data-theme="dark"]` ブロックに
集約予定。Chart.js 等の canvas 描画色は CSS 変数が効かないため、
`frontend/` 側の theme モジュール（TSパレット）が一次ソースとなる。
