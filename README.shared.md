# 共有モード（別リポジトリのローカル環境を共有する2つ目のインスタンス）

## これは何か

**コードはこのリポジトリで動かしつつ、ローカルのデータ実体（MariaDB・storage・comment-img）を
別ディレクトリにある既存リポジトリ側に一本化**する仕組み。

- 既存環境（本番同期済みのローカル）を別ディレクトリに持っている人が、**もう1つ別のディレクトリにこのリポジトリを clone するだけ**で、
  同じデータを参照する2つ目のインスタンスを立てられる。
- 本番同期（prod-sync）は参照先で1回やれば、両リポジトリから同じ最新データが見える（二重同期不要）。
- データは実体共有なので、片方での書き込み（クロール等）はもう片方にも反映される。

## クイックスタート

```bash
# 1. 任意のディレクトリにこのリポジトリを clone
git clone <repo> oc-graph-shared && cd oc-graph-shared

# 2. 起動（初回のみ対話）
make up-shared
#   → 参照先リポジトリのパスを尋ねられる（例: /home/you/repos/Open-Chat-Graph）
#   → ポートが参照先と衝突する場合はプリセット(9000/9443, 10000/10443 ...)から選択
#   → https://localhost:<選んだHTTPSポート> で参照先と同一データが開く

make down-shared   # 停止して通常設定に戻す
```

2回目以降は参照先を尋ねられない（`.shared.local.mk` に保存済み）。

## 仕組み

| 対象 | やり方 |
|---|---|
| MariaDB | app の default ネットワークを参照先の外部ネットワーク（例 `<project>_default`）に差し替え、`local-secrets.php` の `dbHost='mysql'` が参照先の MariaDB を指すようにする（こちらの mysql コンテナは起動しない） |
| storage / comment-img | 参照先の実ディレクトリを bind mount（`./:/var/www/html` より優先。大容量データの複製なし） |
| translation.json | これだけは storage 共有から外し**このリポジトリ側**を使う（翻訳はコード扱い） |
| local-secrets.php | 参照先からコピー（`make shared-setup` / `up-shared` が実施。元は `local-secrets.php.bak` に退避） |
| .env | `DATA_PROTECTION=true`（実データを共有するため）。ポート衝突時は選択値に書き換え |

## ファイル

- `docker-compose.shared.yml` … 参照先ネットワーク直結＋storage/comment-img の bind mount（追跡済み）
- `Makefile` の `up-shared` / `shared-setup` / `down-shared` … 共有モードのターゲット（追跡済み）
- `.shared.local.mk` … 参照先パス・ネットワーク名のローカル保存（**`.gitignore` 済み・環境ごとに異なる**）

## コマンド

```bash
make up-shared      # 起動（初回は参照先を尋ね、ポート衝突時はプリセット選択）
make shared-setup   # 初期設定のみ（参照先登録＋local-secretsコピー＋DATA_PROTECTION=true。起動しない）
make down-shared    # 停止して通常設定に戻す（local-secrets.php 復元 + DATA_PROTECTION=false）
```

## 注意

- `up-shared` は**参照先のコンテナ（＝外部ネットワーク）が起動済み**であることが前提。
  落ちていれば「先に参照先で `make up` を」とエラー案内し、`docker network ls` を表示する。
- 参照先を変えたいときは `.shared.local.mk` を編集（または削除して `make up-shared` で再入力）。
- DB も storage も**実体を共有**しているので、こちらでの書き込みは参照先にも及ぶ。
