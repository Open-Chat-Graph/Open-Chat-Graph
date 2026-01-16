# LINE公式API モックサーバー

開発環境でLINE OpenChat APIをエミュレートするモックサーバー。

## 主な機能

- **2つのモード**:
  - `dynamic`: リアルなデータ変動をシミュレート（大量データ、時間変化）
  - `fixed`: カテゴリ別クローリングテスト用（固定データ、hourIndexベース）
- **多言語対応**: 日本語、繁体字中国語、タイ語
- **外部ネットワーク不要**: ローカルで完結

## セットアップ

```bash
# Mock環境起動
make up-mock

# 動作確認
curl http://localhost:9000/robots.txt
curl "http://localhost:9000/api/category/17?sort=RANKING&limit=40" | jq .
```

## モード切り替え

環境変数 `MOCK_API_TYPE` で制御:

```bash
# Dynamic モード（デフォルト）
MOCK_API_TYPE=dynamic make up-mock

# Fixed モード（テスト用）
MOCK_API_TYPE=fixed make up-mock
```

## エンドポイント

```
GET /robots.txt
GET /api/category/{categoryId}?sort=RANKING&limit=40&ct={token}
GET /api/category/{categoryId}?sort=RISING&limit=40&ct={token}
GET /api/square/{emid}?limit=1
GET /ti/g2/{ticket}
GET /{imageHash}
GET /{imageHash}/preview
```

## テストスクリプト

### CI用テスト（高速・効率的）

```bash
./test-ci.sh
```

- fixedモードで24時間分のテストを実行（23:30開始→翌23:30まで）
- hourIndexベースでデータ変化を制御
- 少量データ（80件/カテゴリ）、遅延なし
- 日常的なテスト・CI環境での使用を想定

### デバッグ用テスト（本番環境に近い設定）

```bash
./test-cron.sh
```

- dynamicモードで24時間分のクローリングを実行（23:30開始→翌23:30まで）
- 大量データ（10万件）、遅延あり、48時間テストに対応
- テストケースが多く、本番環境の挙動を再現

**テスト仕様:**
- 各カテゴリ: ランキング80件、急上昇80件
- 新規ルーム: 毎時間1件（人数10固定）
- 変動ルーム: 8件（人数・順位が決定論的に変化）
- 静的ルーム: 71件（完全固定）
- カテゴリ0: 急上昇のみルーム16件あり（1時間目のみ）
- カテゴリ1以降: 固定メンバールーム16件（1時間目のみ）

## 環境変数

```bash
MOCK_API_TYPE=dynamic          # dynamic または fixed
MOCK_DELAY_ENABLED=0           # 遅延シミュレーション（0=無効、1=有効）
MOCK_RANKING_COUNT=15000       # ランキング生成件数
MOCK_RISING_COUNT=1500         # 急上昇生成件数
```

## ファイル構成

```
public/
├── index.php      # ルーター（MOCK_API_TYPEで分岐）
├── dynamic.php    # 動的データ生成
└── fixed.php      # 固定データ生成（テスト用）
```
