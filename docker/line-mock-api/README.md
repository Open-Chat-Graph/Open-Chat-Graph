# LINE公式API モックサーバー（実データ版）

## 概要

このディレクトリには、開発環境でLINE公式OpenChat APIをエミュレートするモックサーバーが含まれています。

**重要: このバージョンは実際のクローリングデータ(.dat)を使用します。テストデータの生成は不要です。**

## 特徴

- **実データ使用**: `/storage/ja/ranking_position/` の実際のクローリングデータ(.dat)を読み込み
- **動的データ生成**:
  - 毎回ランダムな順序でデータを返す（シャッフル）
  - メンバー数を±10%の範囲で動的に変化させる
  - 同じEMIDのオープンチャットでランキング順位が変動
- **本番同等のデータ量**: カテゴリごとに8,000-9,000件の実データ
- **外部ネットワーク不要**: インターネットに出ずにクローリング処理をテスト

## ディレクトリ構造

```
line-mock-api/
├── Dockerfile              # モックサーバーのDockerイメージ
├── apache.conf             # Apache設定
├── public/
│   └── index.php          # モックAPIエンドポイント（実データ読み込み）
├── inspect-real-data.php  # データ構造調査スクリプト
└── README.md              # このファイル
```

## セットアップ手順

### 1. DEV環境起動（テストデータ生成不要）

```bash
# docker-compose.dev.ymlを使用して起動
docker-compose -f docker-compose.dev.yml --env-file .env.dev up -d
```

起動されるサービス:
- **app**: Webアプリケーション (http://localhost:8100)
- **mysql**: MySQLデータベース (localhost:3308)
- **phpmyadmin**: phpMyAdmin (http://localhost:8180)
- **line-mock-api**: LINE公式APIモックサーバー (http://localhost:9000)

### 2. ホスト名解決の確認

DEV環境では、Dockerコンテナ内の`extra_hosts`設定により、以下のホスト名が自動的にモックサーバー(172.20.0.10)に解決されます。

```
openchat.line.me → 172.20.0.10 (line-mock-api)
line.me → 172.20.0.10
obs.line-scdn.net → 172.20.0.10
```

**追加設定不要**: Dockerコンテナ内で自動的にホスト名が解決されます。

### 3. 動作確認

```bash
# モックサーバーにアクセスしてrobots.txtを確認
curl http://localhost:9000/robots.txt

# ランキングAPIをテスト（カテゴリ17: ゲーム）
curl "http://localhost:9000/api/category/17?sort=RANKING&limit=40&ct=" | jq .

# 急上昇APIをテスト（カテゴリ8: 地域・暮らし）
curl "http://localhost:9000/api/category/8?sort=RISING&limit=40&ct=" | jq .
```

### 4. CRON処理のテスト

```bash
# DEV環境のappコンテナに入る
docker-compose -f docker-compose.dev.yml exec app bash

# CRON処理を実行（モックサーバーから実データを取得）
php /var/www/html/batch/cron/cron_crawling.php
```

## モックサーバーの動作仕様

### データソース

実際のクローリングデータファイルを使用:
```
/var/www/html/storage/ja/ranking_position/ranking/{categoryId}.dat  # ランキング
/var/www/html/storage/ja/ranking_position/rising/{categoryId}.dat   # 急上昇
```

各ファイルは`App\Services\OpenChat\Dto\OpenChatDto`オブジェクトの配列をシリアライズ・gzip圧縮したもの。

### 動的データ生成ロジック

1. **ランダムシャッフル**
   - セッションごとに異なるシード値を生成
   - 同じセッション内では一貫性を保つ
   - カテゴリごとに異なる順序で返す

2. **メンバー数の変動**
   ```php
   $variation = (int)($baseMembers * 0.1); // ±10%
   $memberCount = $baseMembers + mt_rand(-$variation, $variation);
   ```

3. **ランキング順位の変動**
   - シャッフルされた順序に基づいて`rank`フィールドを設定
   - 同じEMIDのオープンチャットが毎回異なる順位で返される

### レスポンス形式（実際のLINE APIと同一）

```json
{
  "squaresByCategory": [
    {
      "category": {
        "id": 17
      },
      "squares": [
        {
          "square": {
            "emid": "ma26MbCNVn36lTMRp8zztKPjRCDucSWz84f5YkujWR9kRN_clMrnGO1TAXk",
            "name": "ぷにぷにお助け＆雑談部屋",
            "desc": "妖怪ウォッチぷにぷに！お助けや雑談などを...",
            "profileImageObsHash": "0hQp13Tcg6Dl8MShwRM3txCDIcU3F3ORd...",
            "emblems": [],
            "joinMethodType": 0,
            "squareState": 0,
            "badges": [],
            "invitationURL": "https://line.me/ti/g2/ma26MbCNVn..."
          },
          "rank": 1,
          "memberCount": 2090,
          "latestMessageCreatedAt": 1736694000000,
          "createdAt": 1663681474000
        }
      ],
      "subcategories": []
    }
  ],
  "continuationTokenMap": {
    "17": "40"
  }
}
```

## モックAPIエンドポイント

### 1. robots.txt
```
GET /robots.txt
```

### 2. ランキングAPI
```
GET /api/category/{categoryId}?sort=RANKING&limit=40&ct={continuationToken}
```

**利用可能なカテゴリID**: 0, 2, 5, 6, 7, 8, 11, 12, 16, 17, 18, 19, 20, 22, 23, 24, 26, 27, 28, 29, 30, 33, 37, 40, 41

**ページネーション**:
- 初回: `ct=` (空文字列)
- 2ページ目: `ct=40`
- 3ページ目: `ct=80`

### 3. 急上昇API
```
GET /api/category/{categoryId}?sort=RISING&limit=40&ct={continuationToken}
```

### 4. スクエア詳細API
```
GET /api/square/{emid}?limit=1
```

### 5. 招待ページHTML
```
GET /ti/g2/{invitationTicket}
GET /{lang}/ti/g2/{invitationTicket}
```

### 6. 画像CDN
```
GET /{imageHash}
GET /{imageHash}/preview
```

## データ量の比較

| カテゴリ | 実データ件数 | 本番API | モックサーバー |
|---------|-------------|---------|---------------|
| 全体 (0) | 〜8,000件 | 〜8,000件 | 全件 |
| ゲーム (17) | 8,920件 | 〜8,880件 | 全件 |
| スポーツ (16) | 〜9,000件 | 〜9,040件 | 全件 |

モックサーバーは実データをそのまま使用するため、本番とほぼ同じデータ量です。

## 実データの調査

データ構造を確認したい場合:

```bash
php docker/line-mock-api/inspect-real-data.php
```

出力例:
```
実際のクローリングデータ調査
=====================================

ファイル: /home/user/oc-review-dev/storage/ja/ranking_position/ranking/17.dat
サイズ: 3131692 bytes

データ型: array
総件数: 8920
```

## トラブルシューティング

### モックサーバーにアクセスできない

1. コンテナが起動しているか確認:
   ```bash
   docker-compose -f docker-compose.dev.yml ps
   ```

2. ログを確認:
   ```bash
   docker-compose -f docker-compose.dev.yml logs line-mock-api
   ```

### データファイルが見つからない

1. 実データの存在確認:
   ```bash
   ls -lh /home/user/oc-review-dev/storage/ja/ranking_position/ranking/
   ```

2. データファイルのマウント確認:
   ```bash
   docker-compose -f docker-compose.dev.yml exec line-mock-api ls -lh /var/www/html/storage/ja/ranking_position/ranking/
   ```

### CRON処理がモックサーバーにアクセスしない

1. `extra_hosts`設定が正しいか確認:
   ```bash
   docker-compose -f docker-compose.dev.yml exec app cat /etc/hosts
   ```

   出力に以下が含まれていること:
   ```
   172.20.0.10 openchat.line.me
   172.20.0.10 line.me
   172.20.0.10 obs.line-scdn.net
   ```

2. 名前解決をテスト:
   ```bash
   docker-compose -f docker-compose.dev.yml exec app ping -c 1 openchat.line.me
   ```

## 本番環境との違い

| 項目 | 本番環境 | DEV環境（モックサーバー） |
|-----|---------|------------------------|
| データソース | LINE公式API（リアルタイム） | ローカル.datファイル（スナップショット） |
| データ順序 | 固定（ランキング順） | ランダム（毎回シャッフル） |
| メンバー数 | リアルタイム値 | 元データ±10%で変動 |
| レート制限 | あり | なし |
| ネットワーク | インターネット | Dockerローカルネットワーク |

## 開発のヒント

### 本番データとの同期

定期的に本番環境から最新のクローリングデータをコピー:

```bash
# 本番サーバーから.datファイルをダウンロード
scp production:/path/to/storage/ja/ranking_position/ranking/*.dat \
    /home/user/oc-review-dev/storage/ja/ranking_position/ranking/
```

### 多言語対応のテスト

台湾版・タイ版のデータを使用する場合:

```bash
# データディレクトリを変更
# docker/line-mock-api/public/index.php の14-15行目を編集
$rankingDataDir = '/var/www/html/storage/tw/ranking_position/ranking';
$risingDataDir = '/var/www/html/storage/tw/ranking_position/rising';
```

### セッションリセット（データを再シャッフル）

```bash
# PHPセッションをクリア
docker-compose -f docker-compose.dev.yml exec line-mock-api rm -rf /tmp/sess_*
```

または、ブラウザのクッキーをクリアしてアクセス。
