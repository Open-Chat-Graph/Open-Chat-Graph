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
    local total=$(( ${#LANG_CODES[@]} * ${#STATIC_LANG_SUBDIRS[@]} )) i=0
    for lang in "${LANG_CODES[@]}"; do
        for sub in "${STATIC_LANG_SUBDIRS[@]}"; do
            i=$((i+1))
            local remote_dir="${REMOTE_PUBLIC_HTML}/storage/${lang}/${sub}/"
            local local_dir="${LOCAL_STORAGE_DIR}/${lang}/${sub}/"
            mkdir -p "$local_dir"
            # rsync 前にローカルを host ユーザー所有へ正規化 (owner/times/perms/unlink 失敗を防ぐ)。
            prepare_local_dir "/var/www/html/storage/${lang}/${sub}"
            log_info "[${i}/${total}] rsync: ${lang}/${sub}/"
            rsync -a --partial --delete --info=progress2 \
                --no-owner --no-group --chmod=Du+rwx,Fu+rw \
                -e "$RSYNC_SSH" \
                "${SSH_TARGET}:${remote_dir}" \
                "$local_dir"
            chmod 755 "$local_dir" 2>/dev/null || true
        done
    done
    log_ok "派生キャッシュ同期完了"
}
