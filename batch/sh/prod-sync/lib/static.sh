#!/usr/bin/env bash

# 派生キャッシュ・状態ファイルの同期
# storage/$lang/{ranking_position,static_data_top,static_data_recommend,open_chat_sub_categories}/
#
# これらはローカル cron 実行で再生成可能だが、即動作させるために本番から取得する。
# 容量は小さい (合計100MB前後)。--delete を付けて古いキャッシュを残さない。
#
# 除外: logs/ (ローカル動作中に自動生成される)

static_rsync_lang_dirs() {
    log_step "Static: 派生キャッシュ同期 (storage/\$lang/...)"
    for lang in "${LANG_CODES[@]}"; do
        for sub in "${STATIC_LANG_SUBDIRS[@]}"; do
            local remote_dir="${REMOTE_PUBLIC_HTML}/storage/${lang}/${sub}/"
            local local_dir="${LOCAL_STORAGE_DIR}/${lang}/${sub}/"
            mkdir -p "$local_dir"
            log_info "rsync: ${lang}/${sub}/"
            rsync -av --partial --delete \
                -e "$RSYNC_SSH" \
                "${SSH_TARGET}:${remote_dir}" \
                "$local_dir"
        done
    done
    log_ok "派生キャッシュ同期完了"
}
