# GitHub Actions（デプロイ）で使う変数・シークレット

`deploy.yml` が参照する GitHub Actions の Variables / Secrets 一覧。
（Repository → Settings → Secrets and variables → Actions で設定）

環境ごとに `STG_` プレフィックスで stg 用を分けている。プレフィックス無し = 本番(main)、`STG_` = stg。

## Variables（値はログに出る・非機密）

| 名前 | 用途 |
|---|---|
| `SSH_HOST` / `STG_SSH_HOST` | デプロイ先サーバーのホスト名 |
| `SSH_PORT` / `STG_SSH_PORT` | SSH ポート |
| `SSH_USER` / `STG_SSH_USER` | SSH ユーザー |
| `SSH_PATH` / `STG_SSH_PATH` | デプロイ先のパス（docroot） |

## Secrets（ログでマスクされる・機密）

| 名前 | 用途 |
|---|---|
| `SSH_PRIVATE_KEY` / `STG_SSH_PRIVATE_KEY` | デプロイ用 SSH 秘密鍵 |
| `SSH_KNOWN_HOSTS` / `STG_SSH_KNOWN_HOSTS` | known_hosts エントリ |
| `CLOUDFLARE_ZONE_ID` / `STG_CLOUDFLARE_ZONE_ID` | キャッシュパージ対象ゾーン |
| `CLOUDFLARE_API_KEY` / `STG_CLOUDFLARE_API_KEY` | キャッシュパージ用 API トークン（権限はパージのみ） |
| `DISCORD_WEBHOOK_URL` | デプロイ成功/失敗通知 |
| `TWITTER_API_KEY` `TWITTER_API_SECRET` `TWITTER_ACCESS_TOKEN` `TWITTER_ACCESS_TOKEN_SECRET` | マージ後の X 自動投稿 |

## ハードコードしていない理由（重要）

- **オリジンIPはどこにも保存しない**。デプロイ後スモークテスト（`scripts/post-deploy-smoke.sh`）は
  Cloudflare の bot チャレンジを避けるためオリジン直叩き（`curl --resolve`）するが、そのオリジンIPは
  スクリプト内で `hostname -I`（=実行中サーバー自身の公開IP）から動的取得する。CF の裏に隠すべき
  オリジンIPを公開リポ/ログに残さないため。
- サイトURL（`https://openchat-review.me` 等）は公開情報なので config ステップ内に直書き。
