#!/usr/bin/env bash

# 派生 DB (ocgraph_sqlapi) のローカル再構築
#
# OcreviewApiDataImporter は MySQL ocgraph_ocreview + SQLite statistics/ranking_position
# から ocgraph_sqlapi (アーカイブ追記専用) を生成する。
# 自前で「前回最終取り込み時点」を記録するため、毎回の差分のみを処理する。
#
# 初回は ocgraph_sqlapi 自体を rsync で取得する (49GB)。
# 更新時は転送せず本関数で差分追記のみ。

derived_run_importer() {
    log_step "Derived: ocgraph_sqlapi 差分インポータ実行"
    docker compose -f "${PROJECT_ROOT}/docker-compose.yml" exec -T app \
        php batch/exec/update_api_db.php
    log_ok "インポータ完了"
}
