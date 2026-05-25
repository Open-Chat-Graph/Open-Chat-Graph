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

# rsync --delete でローカル削除する前の権限正規化。
# 画像のシャードディレクトリ (00/.../ff/) はアプリコンテナが www-data 所有 755 で
# 作成するため、host の sync ユーザー (uid 1000) は親ディレクトリへ書き込めず unlink に
# 失敗する (Permission denied / cannot delete non-empty directory)。
# コンテナ(root)で a+rwX に正規化しておけば host から削除でき、毎環境でこのエラーを踏まない。
# 引数: コンテナ内の絶対パス (バインドマウント先 /var/www/html 配下)。
images_make_writable() {
    local container_path="$1"
    local_app_exec chmod -R a+rwX "$container_path" 2>/dev/null \
        || log_info "warn: 権限正規化をスキップ ($container_path) — app コンテナ未起動?"
}

images_rsync_comment_img() {
    log_step "Images: public/comment-img/"
    local remote_dir="${REMOTE_PUBLIC_HTML}/public/comment-img/"
    local local_dir="${LOCAL_PUBLIC_DIR}/comment-img/"
    mkdir -p "$local_dir"
    images_make_writable "/var/www/html/public/comment-img"
    rsync -a --partial --delete-after --max-delete="$IMAGES_MAX_DELETE" \
        --info=progress2 --chmod=Da+rwx,Fa+rw \
        -e "$RSYNC_SSH" \
        "${SSH_TARGET}:${remote_dir}" \
        "$local_dir"
    chmod 777 "$local_dir" 2>/dev/null || true
    log_ok "comment-img 同期完了"
}

images_rsync_comment_img_hidden() {
    log_step "Images: storage/comment-img-hidden/"
    local remote_dir="${REMOTE_PUBLIC_HTML}/storage/comment-img-hidden/"
    local local_dir="${LOCAL_STORAGE_DIR}/comment-img-hidden/"
    mkdir -p "$local_dir"
    images_make_writable "/var/www/html/storage/comment-img-hidden"
    rsync -a --partial --delete-after --max-delete="$IMAGES_MAX_DELETE" \
        --info=progress2 --chmod=Da+rwx,Fa+rw \
        -e "$RSYNC_SSH" \
        "${SSH_TARGET}:${remote_dir}" \
        "$local_dir"
    chmod 777 "$local_dir" 2>/dev/null || true
    log_ok "comment-img-hidden 同期完了"
}
