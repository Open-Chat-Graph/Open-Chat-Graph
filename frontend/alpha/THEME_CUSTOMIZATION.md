# テーマカスタマイズガイド

このドキュメントでは、オプチャグラフαのカラーテーマをカスタマイズする方法を説明します。

## カラーテーマの概要

アプリケーションのカラーテーマは以下の3つのファイルで管理されています：

1. **`src/index.css`** - Tailwind CSS変数 (UIコンポーネント全般)
2. **`src/lib/theme-colors.ts`** - カラー定義のリファレンス (ドキュメント用)
3. **`/home/user/oc-review-graph/src/util/theme.ts`** - Preactグラフのカラー定義

## カラーテーマの変更手順

### 1. Tailwind CSS変数を変更 (UIコンポーネント)

**ファイル**: `src/index.css`

```css
.dark {
  /* shadcn/ui standard dark mode - Slate theme */
  --background: 222.2 84% 4.9%;      /* #020817 - 背景色 */
  --foreground: 210 40% 98%;         /* #f8fafc - テキスト色 */
  --border: 217.2 32.6% 17.5%;       /* #1e293b - 枠線の色 */
  --input: 217.2 32.6% 17.5%;        /* #1e293b - 入力欄の枠線 */
  --primary: 217.2 91.2% 59.8%;      /* #4c8bf5 - プライマリカラー */
  /* ... その他の変数 */
}
```

**影響範囲**:
- カードの枠線 (border)
- 入力欄の枠線 (input)
- ボタンの色 (primary)
- テキストの色 (foreground, muted-foreground)

### 2. グラフのカラーを変更

**ファイル**: `/home/user/oc-review-graph/src/util/theme.ts`

```typescript
dark: {
  grid: '#1e293b',           // グリッド線の色
  border: '#1e293b',         // 枠線の色
  text: {
    primary: '#ffffff',      // datalabel (数値) の色
    secondary: '#94a3b8',    // 軸ラベルの色
  },
  lineGradient: {            // メンバー数グラフの線の色
    stops: [
      { offset: 1, color: 'rgba(91, 156, 246, 1.0)' },  // 開始色
      // ...
      { offset: 0, color: 'rgba(29, 78, 216, 1.0)' }    // 終了色
    ]
  },
}
```

**影響範囲**:
- グラフのグリッド線
- データラベル (数値表示)
- ライングラデーション
- MUIボタン (タブボタンなど)

### 3. グラフをビルドして反映

```bash
# グラフをビルド
cd /home/user/oc-review-graph
npm run build

# ビルドしたファイルをコピー
cp dist/assets/index-*.js /home/user/oc-review-dev/public/js/preact-chart/assets/index.js
```

### 4. フロントエンドをビルド

```bash
cd /home/user/openchat-alpha
npm run build
```

## 現在使用中のカラーテーマ

### shadcn/ui Slate Theme (ダークモード)

| 要素 | HSL値 | Hex値 | 説明 |
|------|-------|-------|------|
| Background | 222.2 84% 4.9% | #020817 | 非常に暗い青黒 |
| Foreground | 210 40% 98% | #f8fafc | ほぼ白 |
| Border | 217.2 32.6% 17.5% | #1e293b | 暗いがうっすら見える枠線 |
| Input | 217.2 32.6% 17.5% | #1e293b | 入力欄の枠線 |
| Primary | 217.2 91.2% 59.8% | #4c8bf5 | 明るい青 |
| Muted Foreground | 215 20.2% 65.1% | #94a3b8 | ミュートされたグレー |

### グラフ専用カラー

| 要素 | 値 | 説明 |
|------|------|------|
| Grid | #1e293b | グリッド線 (borderと同じ) |
| Datalabel | #ffffff | 数値表示 (純白で最大視認性) |
| Line Gradient | #5b9cf6 → #1d4ed8 | 青のグラデーション |

## トラブルシューティング

### カードの枠線が明るすぎる

`src/index.css` の `--border` 値を暗くします:

```css
--border: 217.2 32.6% 12%;  /* より暗く */
```

### 入力欄の枠線が暗すぎる

`src/index.css` の `--input` 値を明るくします:

```css
--input: 217.2 32.6% 20%;  /* より明るく */
```

### グラフのdatalabelが見えにくい

`/home/user/oc-review-graph/src/util/theme.ts` の `text.primary` を変更:

```typescript
text: {
  primary: '#ffffff',  // 純白
}
```

また、`src/classes/ChartJS/Factories/buildPlugin.ts` で背景色を調整:

```typescript
backgroundColor: colors.pointBackground === '#0f172a'
  ? 'rgba(15, 23, 42, 0.75)'  // 背景の透明度を調整
  : 'rgba(0,0,0,0)',
```

### MUIボタンが見えにくい

`/home/user/oc-review-graph/src/app.tsx` のMUIテーマ設定を変更:

```typescript
'&.MuiButton-outlined': {
  borderColor: '#475569',     // より明るいborder
  borderWidth: '1.5px',       // より太いborder
  color: '#f8fafc',           // より明るいtext
}
```

## 参考リンク

- [shadcn/ui テーマ](https://ui.shadcn.com/themes)
- [Tailwind CSS カラーパレット](https://tailwindcss.com/docs/customizing-colors)
- [Chart.js ドキュメント](https://www.chartjs.org/docs/latest/)
