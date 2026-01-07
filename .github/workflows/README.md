# GitHub Actions Workflows

## post-pr-merge.yml

PRがmainブランチにマージされたときに、X (Twitter) に通知を投稿するワークフローです。

### 必要なシークレット設定

このワークフローを有効にするには、GitHubリポジトリに以下のシークレットを設定する必要があります。

リポジトリの Settings → Secrets and variables → Actions → New repository secret から設定してください。

#### 必須シークレット

1. `TWITTER_API_KEY` - Twitter API Key (Consumer Key)
2. `TWITTER_API_SECRET` - Twitter API Secret (Consumer Secret)
3. `TWITTER_ACCESS_TOKEN` - Twitter Access Token
4. `TWITTER_ACCESS_TOKEN_SECRET` - Twitter Access Token Secret

### Twitter API キーの取得方法

1. [Twitter Developer Portal](https://developer.twitter.com/en/portal/dashboard) にアクセス
2. プロジェクトとアプリを作成
3. アプリの設定から "Keys and tokens" タブを開く
4. API Key and Secret を生成（既に生成済みの場合は表示）
5. Access Token and Secret を生成（Read and Write 権限が必要）
6. 各値をコピーしてGitHubのシークレットに設定

### 投稿されるメッセージ形式

```
✨ プルリクエストがマージされました

#123: プルリクエストのタイトル
By @ユーザー名

https://github.com/owner/repo/pull/123

#オプチャグラフ #OpenChatGraph
```

### トリガー条件

- `main` ブランチへのプルリクエストがマージされたとき
- `workflow_dispatch` による手動実行は未対応

### テスト方法

1. テストブランチを作成してプルリクエストを作成
2. プルリクエストを `main` にマージ
3. Actions タブでワークフローの実行を確認
4. X (Twitter) に投稿されたか確認

### トラブルシューティング

#### ワークフローが実行されない
- PRが本当にマージされたか確認（クローズだけでは実行されません）
- ターゲットブランチが `main` であることを確認

#### Xへの投稿が失敗する
- シークレットが正しく設定されているか確認
- Twitter APIのアクセストークンに Read and Write 権限があるか確認
- APIの利用制限に達していないか確認

#### エラーログの確認方法
1. リポジトリの Actions タブを開く
2. 失敗したワークフローをクリック
3. "Post to X (Twitter)" ステップのログを確認
