#!/usr/bin/env bash

# SQLite 同期
# - checkpoint_remote : リモートで PRAGMA wal_checkpoint(TRUNCATE) を実行
# - rsync_dbs         : storage/$lang/SQLite/$dir/ を rsync 差分転送
#   * 初回 (include_sqlapi=true) は ocgraph_sqlapi も対象に含める
#   * 更新 (include_sqlapi=false) は ocgraph_sqlapi を除外 (派生として後で再構築)
#
# rsync は default mode (temp+rename) を使う:
#   - --inplace を使わないことでローカルアプリ稼働中の SQLite 読み込みを壊さない
#   - その代わり一時的に約2倍のディスクが必要
#
# 注: --delete はディレクトリ単位の rsync では「同期元に無いファイルを削除」を意味し、
# 単一ファイルの転送には影響しない。ここでは念のため統一的に付ける。

sqlite_remote_db_path() {
    local lang="$1" dir="$2"
    echo "${REMOTE_PUBLIC_HTML}/storage/${lang}/SQLite/${dir}"
}

sqlite_local_db_path() {
    local lang="$1" dir="$2"
    echo "${LOCAL_STORAGE_DIR}/${lang}/SQLite/${dir}"
}

# リモートで WAL を本体DBへチェックポイント (TRUNCATE で .db-wal をリセット)
sqlite_checkpoint_remote() {
    local include_sqlapi="${1:-false}"
    log_step "SQLite: リモートで WAL チェックポイント"

    local dirs=("${SQLITE_DIRS[@]}")
    if [ "$include_sqlapi" = "true" ]; then
        dirs+=("$SQLITE_SQLAPI_DIR")
    fi

    for lang in "${LANG_CODES[@]}"; do
        for dir in "${dirs[@]}"; do
            local remote_dir
            remote_dir=$(sqlite_remote_db_path "$lang" "$dir")
            # 該当ディレクトリ内の *.db を全部 checkpoint
            "${SSH_CMD[@]}" "$SSH_TARGET" "bash -s $(remote_quote "$remote_dir")" <<'EOF' || true
remote_dir=$1
shopt -s nullglob
for f in "$remote_dir"/*.db; do
    sqlite3 "$f" 'PRAGMA wal_checkpoint(TRUNCATE);' >/dev/null 2>&1 || true
done
EOF
        done
    done
    log_ok "チェックポイント完了"
}

# SQLite ファイルを rsync 差分転送
sqlite_rsync_dbs() {
    local include_sqlapi="${1:-false}"
    log_step "SQLite: rsync 差分転送 (include_sqlapi=$include_sqlapi)"

    local dirs=("${SQLITE_DIRS[@]}")
    if [ "$include_sqlapi" = "true" ]; then
        dirs+=("$SQLITE_SQLAPI_DIR")
    fi

    for lang in "${LANG_CODES[@]}"; do
        for dir in "${dirs[@]}"; do
            local remote_dir local_dir
            remote_dir=$(sqlite_remote_db_path "$lang" "$dir")
            local_dir=$(sqlite_local_db_path "$lang" "$dir")
            mkdir -p "$local_dir"
            log_info "rsync: ${lang}/${dir}/"
            # --include='*.db' --exclude='*' : .db のみ転送 (.db-wal/.db-shm は除外)
            # ローカル側で WAL/SHM が古いと不整合になるので削除
            rm -f "${local_dir}"/*.db-wal "${local_dir}"/*.db-shm 2>/dev/null || true
            rsync -av --partial --delete \
                --include='*.db' --exclude='*' \
                -e "$RSYNC_SSH" \
                "${SSH_TARGET}:${remote_dir}/" \
                "${local_dir}/"
        done
    done
    log_ok "SQLite rsync 完了"
}
