# GitHub Actions Workflows

## ci.yml
PRマージ前のテスト実行
- Mock環境でクローリング・URLテストを実行
- ローカルでも `make ci-test` で同じテストを実行可能

## post-pr-merge.yml
PRマージ時にX (Twitter) に通知を投稿

**必要なシークレット:**
- `TWITTER_API_KEY`
- `TWITTER_API_SECRET`
- `TWITTER_ACCESS_TOKEN`
- `TWITTER_ACCESS_TOKEN_SECRET`

**投稿制御:**
- `skip-post` ラベル: 投稿をスキップ
- draft PR: 自動的にスキップ
