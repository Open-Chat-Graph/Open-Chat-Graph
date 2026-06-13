#!/usr/bin/env bash

# 本番同期スクリプト共通設定
# setup.sh / update.sh から `source` される
#
# 期待される事前状態:
#   - PROD_SYNC_DIR がスクリプトの存在ディレクトリ (batch/sh/prod-sync) を指す
#   - PROJECT_ROOT がプロジェクトルートを指す
#   - secrets/prod-sync.env, secrets/prod-ssh.key が配置済み

set -u  # 未定義変数の使用を即エラーに（呼び出し側で必要なら set -e も）

# ============================================
# パス
# ============================================
# 機密ファイル群は ${PROD_SYNC_DIR}/secrets/ にプライベートリポ(oc-infra)を git clone する形で配置される。
# (Makefile の sync-setup / sync-update が自動 fetch する)
SECRETS_DIR="${PROD_SYNC_DIR}/secrets"
PROD_SYNC_ENV="${SECRETS_DIR}/prod-sync/prod-sync.env"
PROD_SSH_KEY="${SECRETS_DIR}/servers/ssh/ocgraph.key"
LOCAL_SECRETS_TMPL="${SECRETS_DIR}/prod-sync/local-secrets.tmpl.php"

LOCAL_SQLDUMP_DIR="${PROJECT_ROOT}/batch/sh/sqldump"
LOCAL_STORAGE_DIR="${PROJECT_ROOT}/storage"
LOCAL_PUBLIC_DIR="${PROJECT_ROOT}/public"

# ============================================
# 機密ファイルの存在検証
# ============================================
for _f in "$PROD_SYNC_ENV" "$PROD_SSH_KEY"; do
    if [ ! -f "$_f" ]; then
        echo "Error: 必要なファイルが見つかりません: $_f" >&2
        echo "       make sync-setup / sync-update を経由していますか?" >&2
        echo "       プライベートリポへのアクセス権が必要です。" >&2
        exit 1
    fi
done
chmod 600 "$PROD_SSH_KEY" 2>/dev/null || true
chmod 600 "$PROD_SYNC_ENV" 2>/dev/null || true

# secrets を読み込む
# shellcheck disable=SC1090
source "$PROD_SYNC_ENV"

# 必須変数チェック
_required_vars=(
    REMOTE_SERVER REMOTE_USER REMOTE_PORT
    REMOTE_PUBLIC_HTML REMOTE_MYSQL_USER REMOTE_MYSQL_PASS REMOTE_DUMP_DIR
    LOCAL_MYSQL_USER LOCAL_MYSQL_PASS
)
for _v in "${_required_vars[@]}"; do
    if [ -z "${!_v:-}" ]; then
        echo "Error: prod-sync.env に必須変数 $_v が定義されていません。" >&2
        exit 1
    fi
done

# ============================================
# 定数（プロジェクト規約として固定）
# ============================================

# 言語コード（storage/$lang/ のサブディレクトリ）
LANG_CODES=(ja tw th)

# MySQL データベース一覧（リモート名＝ローカル名で運用）
MYSQL_DBS=(
    ocgraph_ocreview
    ocgraph_ocreviewtw
    ocgraph_ocreviewth
    ocgraph_ranking
    ocgraph_rankingtw
    ocgraph_rankingth
    ocgraph_userlog
    ocgraph_comment
    ocgraph_commenttw
    ocgraph_commentth
)

# コメント画像保持のためのテーブル定義（存在しなければ作成）
COMMENT_IMAGE_DBS=(ocgraph_comment ocgraph_commenttw ocgraph_commentth)
COMMENT_IMAGE_CREATE_SQL="CREATE TABLE IF NOT EXISTS comment_image (
  id int NOT NULL AUTO_INCREMENT,
  comment_id int NOT NULL,
  filename varchar(255) NOT NULL,
  sort_order tinyint NOT NULL DEFAULT 0,
  created_at datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY idx_comment_id (comment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"

# SQLite DB のサブディレクトリ名（storage/$lang/SQLite/ 配下）
# 注意: ocgraph_sqlapi は派生（アーカイブ追記専用）なので別扱い
SQLITE_DIRS=(
    statistics
    ranking_position
    statistics_ohlc
    ranking_position_ohlc
    oc_page_cache
)
SQLITE_SQLAPI_DIR="ocgraph_sqlapi"

# storage 配下の非SQLiteサブディレクトリ（言語ごと）
STATIC_LANG_SUBDIRS=(
    ranking_position
    static_data_top
    static_data_recommend
    open_chat_sub_categories
)

# ============================================
# SSH / rsync 共通オプション
# ============================================
# OpenSSH 10+ は PQ 鍵交換 未対応サーバへの接続のたびに警告を出す（本番サーバが該当）。
# WarnWeakCrypto=no-pq-kex でこの警告だけ抑止する（他の弱い暗号警告は残す）。
# 古いクライアントは未知オプションで落ちるため、サポートする場合のみ付与する。
SSH_NO_PQ_WARN=""
if ssh -G -o WarnWeakCrypto=no-pq-kex none >/dev/null 2>&1; then
    SSH_NO_PQ_WARN="-o WarnWeakCrypto=no-pq-kex"
fi

SSH_CMD=(ssh -o StrictHostKeyChecking=accept-new $SSH_NO_PQ_WARN -i "$PROD_SSH_KEY" -p "$REMOTE_PORT")
SSH_TARGET="${REMOTE_USER}@${REMOTE_SERVER}"
RSYNC_SSH="ssh -o StrictHostKeyChecking=accept-new ${SSH_NO_PQ_WARN} -i ${PROD_SSH_KEY} -p ${REMOTE_PORT}"

# ============================================
# 共通ヘルパー
# ============================================

# ローカル MySQL コマンド (mysql コンテナ経由)
local_mysql() {
    docker compose -f "${PROJECT_ROOT}/docker-compose.yml" exec -T mysql \
        env MYSQL_PWD="$LOCAL_MYSQL_PASS" mysql -u"$LOCAL_MYSQL_USER" "$@"
}

# app コンテナ(root)でコマンドを実行する (app コンテナ経由)
# host とバインドマウント (./:/var/www/html) を共有するため、
# コンテナ内 root で chmod すれば host 側の同じ inode に反映される。
# アプリ(www-data)が作成し host の sync ユーザーが触れないファイルの権限調整に使う。
local_app_exec() {
    docker compose -f "${PROJECT_ROOT}/docker-compose.yml" exec -T app "$@"
}

# host 側 sync ユーザーの uid:gid。コンテナ(root)から chown する際の所有者。
HOST_UID="$(id -u)"
HOST_GID="$(id -g)"

# rsync 前のローカルディレクトリ権限正規化。
# アプリ(www-data)が作成したファイル/ディレクトリは host の sync ユーザーが所有しておらず、
# rsync -a が owner/group/times/perms を本番へ合わせようとして "Operation not permitted" で
# 失敗する。また親ディレクトリに書き込めず --delete の unlink も失敗する。
# コンテナ(root)で host ユーザー所有へ chown + owner 書き込み可へ chmod しておけば、以降 rsync は
# 全属性を自由に設定でき、どの環境でもこれらのエラーを踏まない。
# 同期後も www-data は entrypoint で host UID に揃えてあるため owner perms(u+rwX)だけで
# read/write/delete でき、777/666 のような world-write は不要。
# 引数: コンテナ内パス (バインドマウント先 /var/www/html 配下)。
prepare_local_dir() {
    local p="$1"
    local_app_exec sh -c "chown -R ${HOST_UID}:${HOST_GID} '$p' && chmod -R u+rwX '$p'" 2>/dev/null \
        || log_info "warn: 権限正規化をスキップ ($p) — app コンテナ未起動?"
}

# ローカル MySQL に SQL ファイルを流し込む (mysql コンテナ経由)
# 第1引数: データベース名, 第2引数: SQL ファイルパス (ホスト側)
#
# foreign_key_checks / unique_checks を無効化して高速化 (dump は整合性ある前提)。
local_mysql_import() {
    local db="$1"
    local sql_file="$2"
    {
        printf 'SET foreign_key_checks=0;\nSET unique_checks=0;\n'
        cat "$sql_file"
    } | docker compose -f "${PROJECT_ROOT}/docker-compose.yml" exec -T mysql \
        env MYSQL_PWD="$LOCAL_MYSQL_PASS" mysql -u"$LOCAL_MYSQL_USER" "$db"
}

# 進捗ログ
log_step() {
    echo ""
    echo "----------------------------------------"
    echo "$1"
    echo "----------------------------------------"
}

log_info() { echo "  $*"; }
log_ok()   { echo "  ✓ $*"; }

# リモートコマンド実行用に引数を安全にシングルクォートで囲む
remote_quote() { printf "'%s' " "$@"; }
