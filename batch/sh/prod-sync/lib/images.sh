#!/usr/bin/env bash

# 画像同期
# - public/comment-img/      : コメント投稿画像 (ユーザーアップロード, 真実ソース)
# - storage/comment-img-hidden/ : モデレートで非公開化された画像
#
# 両方ともファイル名先頭2文字でシャード分散 (00/, 01/, ..., ff/)。
# --delete-after + --max-delete=N で:
#   - 本番で削除された画像はローカルでも反映 (整合性)
#   - 本番障害で異常に多くの画像が一時消失したケースで巻き添えを防ぐ (--max-delete)

# 1回の同期で削除許容する最大ファイル数。これを超える削除は安全装置として停止。
IMAGES_MAX_DELETE=10000

images_rsync_comment_img() {
    log_step "Images: public/comment-img/"
    local remote_dir="${REMOTE_PUBLIC_HTML}/public/comment-img/"
    local local_dir="${LOCAL_PUBLIC_DIR}/comment-img/"
    mkdir -p "$local_dir"
    prepare_local_dir "/var/www/html/public/comment-img"
    rsync -a --partial --delete-after --max-delete="$IMAGES_MAX_DELETE" \
        --info=progress2 --no-owner --no-group --chmod=Du+rwx,Fu+rw \
        -e "$RSYNC_SSH" \
        "${SSH_TARGET}:${remote_dir}" \
        "$local_dir"
    chmod 755 "$local_dir" 2>/dev/null || true
    log_ok "comment-img 同期完了"
}

images_rsync_comment_img_hidden() {
    log_step "Images: storage/comment-img-hidden/"
    local remote_dir="${REMOTE_PUBLIC_HTML}/storage/comment-img-hidden/"
    local local_dir="${LOCAL_STORAGE_DIR}/comment-img-hidden/"
    mkdir -p "$local_dir"
    prepare_local_dir "/var/www/html/storage/comment-img-hidden"
    rsync -a --partial --delete-after --max-delete="$IMAGES_MAX_DELETE" \
        --info=progress2 --no-owner --no-group --chmod=Du+rwx,Fu+rw \
        -e "$RSYNC_SSH" \
        "${SSH_TARGET}:${remote_dir}" \
        "$local_dir"
    chmod 755 "$local_dir" 2>/dev/null || true
    log_ok "comment-img-hidden 同期完了"
}
