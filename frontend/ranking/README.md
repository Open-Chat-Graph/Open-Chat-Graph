# frontend/ranking

オプチャグラフのランキングページ（React SPA）

公開ページ: https://openchat-review.me/ranking

## 技術スタック

| 分類 | ライブラリ | バージョン |
|---|---|---|
| ビルド | Vite | 6 |
| UI | React | 19 |
| 型 | TypeScript | 5 |
| 状態管理 | Jotai | 2 |
| UIコンポーネント | MUI (Material UI) | 6 |
| ルーティング | react-router-dom | 7 |
| データ取得 | SWR | 2 |
| カルーセル | Swiper | 11 |
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
