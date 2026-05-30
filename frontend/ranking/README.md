# frontend/ranking

オプチャグラフのランキングページ（React SPA）

公開ページ: https://openchat-review.me/ranking

## 技術スタック

| 分類 | ライブラリ | バージョン |
|---|---|---|
| ビルド | Vite | 7 |
| UI | React | 19 |
| 型 | TypeScript | 5 |
| 状態管理 | Jotai | 2 |
| UIコンポーネント | MUI (Material UI) | 7 |
| ルーティング | react-router-dom | 7 |
| データ取得 | SWR | 2 |
| カルーセル | Swiper | 12 |
| テスト | Vitest + Testing Library | - |

## コマンド

```bash
# 開発サーバー
npm run dev

# ビルド（JS → public/js/react/, CSS → public/style/react/）
npm run build

# テスト実行
npm run test

# テスト（watchモード）
npm run test:watch
```

## テスト

```bash
npm run test          # 全テスト実行
npm run test:watch    # ファイル変更時に自動再実行
```

### テスト一覧

| ファイル | 内容 |
|---|---|
| `atoms.test.ts` | Jotai atomの初期値とread/writeの動作確認 |
| `ListParams.test.ts` | URLパラメータからランキングの表示条件（ソート順・リスト種別等）を正しくパースできるか検証。不正な値が来た場合のフォールバックも確認 |
| `SearchForm.test.tsx` | 検索ボタンの表示・クリックで検索フォームが開くことを確認 |
| `OCListPage.test.tsx` | ランキングページが各ルート（`/ranking`, `/ranking/20`）で正常にレンダリングされるか、無効なカテゴリで404にリダイレクトされるかを確認 |
