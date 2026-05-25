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
# --delete はディレクトリ転送なので「リモートに無い同名ファイルをローカルからも削除」する。
# 古い WAL/SHM 残骸を綺麗にする目的で付ける。

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

    local total=$(( ${#LANG_CODES[@]} * ${#dirs[@]} )) i=0
    for lang in "${LANG_CODES[@]}"; do
        for dir in "${dirs[@]}"; do
            i=$((i+1))
            local remote_dir
            remote_dir=$(sqlite_remote_db_path "$lang" "$dir")
            log_info "[${i}/${total}] checkpoint: ${lang}/${dir}"
            # 該当ディレクトリ内の *.db を全部 checkpoint。
            # 個別ファイルの checkpoint 失敗は heredoc 内で || true。
            # SSH 接続自体の失敗は表に出したいので外側 || true は付けない。
            "${SSH_CMD[@]}" "$SSH_TARGET" "bash -s $(remote_quote "$remote_dir")" <<'EOF'
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

    local total=$(( ${#LANG_CODES[@]} * ${#dirs[@]} )) i=0
    for lang in "${LANG_CODES[@]}"; do
        for dir in "${dirs[@]}"; do
            i=$((i+1))
            local remote_dir local_dir
            remote_dir=$(sqlite_remote_db_path "$lang" "$dir")
            local_dir=$(sqlite_local_db_path "$lang" "$dir")
            # リモートにディレクトリが無ければスキップ。
            # ocgraph_sqlapi は派生（アーカイブ追記専用）で、仕様上 ja のみ存在し
            # tw/th には無い。存在しない転送元を rsync すると change_dir で
            # code 23 を返して set -e で全体が中断するため、事前に存在確認する。
            if ! "${SSH_CMD[@]}" "$SSH_TARGET" "[ -d $(remote_quote "$remote_dir") ]" 2>/dev/null; then
                log_info "[${i}/${total}] skip (リモートに無し): ${lang}/${dir}/"
                continue
            fi
            mkdir -p "$local_dir"
            # rsync 前にローカルを host ユーザー所有へ正規化 (owner/times/perms/unlink 失敗を防ぐ)。
            prepare_local_dir "/var/www/html/storage/${lang}/SQLite/${dir}"
            log_info "[${i}/${total}] rsync: ${lang}/${dir}/"
            # --include='*.db' --exclude='*' : .db のみ転送 (.db-wal/.db-shm は除外)
            # ローカル側で WAL/SHM が古いと不整合になるので削除
            rm -f "${local_dir}"/*.db-wal "${local_dir}"/*.db-shm 2>/dev/null || true
            # --chmod: SQLite は WAL/SHM をディレクトリ内に作るためアプリ(www-data)が
            # ディレクトリと .db ファイルへ書き込みできる必要がある。
            # rsync 後にディレクトリも 777 にして www-data の書き込みを許可。
            # --info=progress2 : 1ファイル単位でなく全体の live%を出す
            rsync -a --partial --delete --info=progress2 \
                --no-owner --no-group \
                --include='*.db' --exclude='*' \
                --chmod=Da+rwx,Fa+rw \
                -e "$RSYNC_SSH" \
                "${SSH_TARGET}:${remote_dir}/" \
                "${local_dir}/"
            chmod 777 "$local_dir" 2>/dev/null || true
        done
    done
    log_ok "SQLite rsync 完了"
}
