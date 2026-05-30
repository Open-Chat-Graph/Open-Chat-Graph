# オプチャグラフα (alpha フロントエンド)

LINE オープンチャットの統計を見る新フロントエンド。React 19 + Vite + TypeScript の SPA で、
Open-Chat-Graph モノレポの一部として `frontend/alpha/` に同居する（旧 standalone リポジトリ
`openchat-alpha` は廃止）。本番では `/alpha` で配信される PWA。

## 開発

```bash
# このディレクトリで
npm install
npm run dev      # Vite 開発サーバ（/alpha-api などは .env の HTTPS_PORT へプロキシ）
```

ローカルのPHPサーバ（Docker, 既定 https://localhost:8443）が動いている必要がある。
`npm run dev` の proxy が `/alpha-api`・`/oc`・`/js` をそこへ転送する。

## ビルド

```bash
npm run build                       # → ../../public/js/alpha に出力
# またはモノレポ直下から
make build-frontend:alpha           # alpha だけ
make build-frontend                 # frontend/* を全部
```

出力 `public/js/alpha/` はリポジトリにコミットする（git ベース配信のため）。
配信ページの PHP シェルは `app/Views/alpha_content.php`、ルートは `app/Config/routing.php`
の `alpha` / `alpha-api` 群。

## 構成

- `src/pages/` … 検索 / マイリスト / 通知 / 詳細 / 設定
- `src/components/Layout/` … `DashboardLayout`(シェル), `HeaderSearchBar`, `MobileBottomNav`, `DetailOverlay`
- `src/components/Detail/` … 詳細ページ部品（`PreactChart` は外部グラフバンドルのラッパー）
- `src/contexts/layout-context.tsx` … ヘッダータイトル・検索再実行シグナルの共有
- `src/api/alpha.ts` … `/alpha-api/*` クライアント、`src/services/storage.ts` … マイリスト(localStorage)
- `src/lib/` … 画像URL(`imageUrl.ts`=公式CDN obs.line-scdn.net)、ソート定義、ストレージキー

## 設計メモ

- ページ切替は「保持パターン」: 検索/マイリスト/通知/設定は常時マウントし React 19 の
  `<Activity>` で表示を切替えてスクロール・状態を保持。詳細ページだけ上に被せるオーバーレイ。
  （背景: 作者記事 https://qiita.com/pikachu0203/items/e26ea70f92eab7d17642 ）
- PWA: `vite-plugin-pwa`。SW は `/js/alpha/sw.js` を scope `/alpha` で登録（Apache が
  `Service-Worker-Allowed: /` を付与）。
- 認証は将来「認証ユーザーのみ」を想定。現状は未導入。通知もサーバ購読を持たず端末ローカル完結。
