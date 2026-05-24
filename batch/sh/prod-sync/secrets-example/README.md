# prod-sync 機密リポのサンプル構造

このディレクトリは `make sync-setup` / `make sync-update` 実行時に
プライベートリポから自動取得される機密ファイル群の **期待される構造とフォーマット** を示すサンプル。

実機での機密は同 `batch/sh/prod-sync/secrets/` 配下に git clone される (gitignored)。

## 必要なファイル

```
secrets/
├── prod-sync/
│   ├── prod-sync.env             # 接続情報 (REMOTE_SERVER, パスワード等)
│   └── local-secrets.tmpl.php    # PHP 設定テンプレ (envsubst で展開される)
└── ssh/
    └── ocgraph.key               # 本番サーバ SSH 秘密鍵 (chmod 600)
```

## 動作

1. `make sync-setup` 初回:
   - `secrets/.git` が無ければプライベートリポを clone (URL は `PROD_SYNC_CONFIG_URL` で上書き可)
   - clone 後に上記3ファイルが揃っていることを検証
   - `local-secrets.tmpl.php` を envsubst で `local-secrets.php` に展開
2. `make sync-update` 以降:
   - `secrets/.git` が既にあれば fetch + reset で最新化
   - 上記3ファイルを使って rsync 差分転送 → ローカル取り込み

アクセス権が無い場合は `git clone` が失敗するので、必然的に sync は使えない。
