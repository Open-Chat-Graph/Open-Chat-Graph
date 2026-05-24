#!/usr/bin/env bash

# MySQL 同期
# - check_remote_dbs : リモートに対象DBが存在することを検証
# - check_local_dbs  : ローカルに対象DBが存在することを検証
# - dump_remote      : リモートで mysqldump を実行 (安定出力フラグ付)
# - rsync_dumps      : ダンプをローカルへ rsync 差分転送
# - import_local     : ローカル MySQL に DROP+CREATE 再インポート
# - ensure_comment_image_table : comment_image テーブルがなければ作成

mysql_check_remote_dbs() {
    log_step "MySQL: リモート DB 存在確認"
    local missing
    missing=$(
        "${SSH_CMD[@]}" "$SSH_TARGET" "bash -s $(remote_quote "$REMOTE_MYSQL_USER" "$REMOTE_MYSQL_PASS" "${MYSQL_DBS[@]}")" <<'EOF'
set -eo pipefail
mysql_user=$1
mysql_pass=$2
shift 2
for db in "$@"; do
    exists=$(MYSQL_PWD="$mysql_pass" mysql -u"$mysql_user" \
        -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$db';" -sN)
    [ -z "$exists" ] && echo "MISSING:$db"
done
EOF
    )
    if [ -n "$missing" ]; then
        echo "Error: リモートに以下のDBがありません:" >&2
        echo "$missing" | sed 's/^MISSING:/  - /' >&2
        return 1
    fi
    log_ok "リモート DB 全て存在 (${#MYSQL_DBS[@]} 個)"
}

mysql_check_local_dbs() {
    log_step "MySQL: ローカル DB 存在確認"
    for db in "${MYSQL_DBS[@]}"; do
        exists=$(local_mysql -sN \
            -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$db';" 2>/dev/null || true)
        if [ -z "$exists" ]; then
            log_info "DB 作成: $db"
            local_mysql -e "CREATE DATABASE \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        fi
    done
    log_ok "ローカル DB 全て準備完了"
}

mysql_dump_remote() {
    log_step "MySQL: リモートでダンプ生成 (--order-by-primary --skip-extended-insert --single-transaction)"
    "${SSH_CMD[@]}" "$SSH_TARGET" "mkdir -p '${REMOTE_DUMP_DIR}' && rm -rf '${REMOTE_DUMP_DIR}'/*"

    for db in "${MYSQL_DBS[@]}"; do
        log_info "ダンプ中: $db"
        "${SSH_CMD[@]}" "$SSH_TARGET" "bash -s $(remote_quote "$REMOTE_MYSQL_USER" "$REMOTE_MYSQL_PASS" "$db" "$REMOTE_DUMP_DIR")" <<'EOF'
set -eo pipefail
mysql_user=$1
mysql_pass=$2
db=$3
dump_dir=$4
MYSQL_PWD="$mysql_pass" mysqldump -u"$mysql_user" \
    --add-drop-table \
    --order-by-primary \
    --skip-extended-insert \
    --single-transaction \
    --databases "$db" > "$dump_dir/$db.sql"
EOF
    done
    log_ok "ダンプ生成完了 (${#MYSQL_DBS[@]} DB)"
}

mysql_rsync_dumps() {
    log_step "MySQL: ダンプをローカルへ rsync 差分転送"
    mkdir -p "$LOCAL_SQLDUMP_DIR"
    rsync -avz --partial \
        -e "$RSYNC_SSH" \
        "${SSH_TARGET}:${REMOTE_DUMP_DIR}/" \
        "${LOCAL_SQLDUMP_DIR}/"
    log_ok "rsync 完了"
}

mysql_import_local() {
    log_step "MySQL: ローカル DB に再インポート (DROP+CREATE)"
    for db in "${MYSQL_DBS[@]}"; do
        local sql_file="${LOCAL_SQLDUMP_DIR}/${db}.sql"
        if [ ! -f "$sql_file" ]; then
            echo "Error: ダンプファイルがありません: $sql_file" >&2
            return 1
        fi
        log_info "インポート中: $db"
        local_mysql_import "$db" "$sql_file"
    done
    log_ok "インポート完了"
}

mysql_ensure_comment_image_table() {
    log_step "MySQL: comment_image テーブル存在保証"
    for db in "${COMMENT_IMAGE_DBS[@]}"; do
        local_mysql "$db" -e "$COMMENT_IMAGE_CREATE_SQL"
        log_ok "${db}.comment_image"
    done
}
