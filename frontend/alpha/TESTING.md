# E2Eテストガイド

このプロジェクトでは、Playwrightを使用したE2Eテストを提供しています。

## テストの種類

### 高速版（Fast）
フロントエンド変更時に常に実行する重要テスト（目安：5分以内）

**含まれるテスト**:
- `core-navigation.spec.ts` - コアナビゲーションパターン
- `critical-ux.spec.ts` - 重要なUX（検索とナビゲーション）
- `detail-page-buttons.spec.ts` - 詳細ページのボタン動作
- `layout-responsiveness.spec.ts` - レイアウトレスポンシブ対応
- `page-rerender-on-reclick.spec.ts` - ページ再レンダリング

### 網羅版（Full）
包括的なテスト（すべてのE2Eテスト）

上記の高速版に加えて：
- `browser-navigation.spec.ts` - ブラウザナビゲーション
- `detail-page-navigation.spec.ts` - 詳細ページナビゲーション
- `graph-display-repeated-navigation.spec.ts` - グラフ表示の繰り返しナビゲーション
- `mylist-file-explorer.spec.ts` - マイリストファイルエクスプローラー
- `mylist-folder-url-navigation.spec.ts` - マイリストフォルダーURLナビゲーション
- `mylist-toolbar-spacing.spec.ts` - マイリストツールバースペーシング
- `navigation-and-buttons.spec.ts` - ナビゲーションとボタン
- `performance-check.spec.ts` - パフォーマンスチェック
- `scroll-persistence.spec.ts` - スクロール永続化
- `scroll-restoration.spec.ts` - スクロール復元

## コマンド

```bash
# 高速版テストを実行（デフォルト）
npm test

# 高速版テストを実行（明示的）
npm run test:fast

# 網羅版テストを実行
npm run test:full

# UIモードでテストを実行（デバッグ用）
npm run test:ui
```

## ヘッドレスモード

すべてのテストはデフォルトでヘッドレスモード（ブラウザUIなし）で実行されます。これにより、CI/CD環境やバックグラウンドでの高速実行が可能です。

設定: `playwright.config.ts` の `headless: true`

## ベストプラクティス

### フロントエンド変更時
1. コードを変更したら、まず `npm run test:fast` を実行
2. すべてパスしたら、コミット前に `npm run test:full` を実行

### テストが失敗したら
1. `npm run test:ui` でUIモードを起動
2. 失敗したテストを選択してデバッグ
3. ステップバイステップで問題を特定

### 新しいテストを追加する場合
- 重要な機能のテスト → `playwright.config.ts` の `fast` プロジェクトに追加
- 詳細な動作検証 → `full` プロジェクトのみに含める（自動的に含まれる）

## CI/CD統合

GitHub ActionsなどのCI環境では、以下のように使い分けます：

```yaml
# プルリクエスト時：高速版のみ
- name: Run fast tests
  run: npm run test:fast

# main/masterブランチへのマージ時：網羅版
- name: Run full tests
  run: npm run test:full
```
