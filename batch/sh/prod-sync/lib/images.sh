#!/usr/bin/env bash

# 画像同期
# - public/comment-img/      : コメント投稿画像 (ユーザーアップロード, 真実ソース)
# - storage/comment-img-hidden/ : モデレートで非公開化された画像
#
# 両方ともファイル名先頭2文字でシャード分散 (00/, 01/, ..., ff/)。
# --delete を付け、本番で削除された画像はローカルでも反映する (整合性優先)。

images_rsync_comment_img() {
    log_step "Images: public/comment-img/"
    local remote_dir="${REMOTE_PUBLIC_HTML}/public/comment-img/"
    local local_dir="${LOCAL_PUBLIC_DIR}/comment-img/"
    mkdir -p "$local_dir"
    rsync -av --partial --delete \
        -e "$RSYNC_SSH" \
        "${SSH_TARGET}:${remote_dir}" \
        "$local_dir"
    log_ok "comment-img 同期完了"
}

images_rsync_comment_img_hidden() {
    log_step "Images: storage/comment-img-hidden/"
    local remote_dir="${REMOTE_PUBLIC_HTML}/storage/comment-img-hidden/"
    local local_dir="${LOCAL_STORAGE_DIR}/comment-img-hidden/"
    mkdir -p "$local_dir"
    rsync -av --partial --delete \
        -e "$RSYNC_SSH" \
        "${SSH_TARGET}:${remote_dir}" \
        "$local_dir"
    log_ok "comment-img-hidden 同期完了"
}
