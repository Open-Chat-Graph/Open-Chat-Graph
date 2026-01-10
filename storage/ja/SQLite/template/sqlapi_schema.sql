-- SQLite schema for ocgraph_sqlapi database
-- Migrated from MySQL with full comment preservation
-- Generated: 2026-01-10

-- ==============================================================================
-- カテゴリマスタテーブル（25レコード）
-- ==============================================================================
CREATE TABLE IF NOT EXISTS categories (
    category_id INTEGER PRIMARY KEY,        -- カテゴリID
    category_name TEXT NOT NULL             -- カテゴリ名
);

-- ==============================================================================
-- オープンチャットのメンバー数統計（毎日1件、オープンチャットIDと日付でユニーク）約6000万レコード以上
-- ==============================================================================
CREATE TABLE IF NOT EXISTS daily_member_statistics (
    record_id INTEGER PRIMARY KEY,          -- レコードID
    openchat_id INTEGER NOT NULL,           -- オプチャグラフでオープンチャットを識別するための主キー（openchat_masterと紐づく）
    member_count INTEGER NOT NULL,          -- メンバー数
    statistics_date TEXT NOT NULL           -- 統計日
);
CREATE INDEX IF NOT EXISTS idx_daily_stats_openchat ON daily_member_statistics(openchat_id);
CREATE INDEX IF NOT EXISTS idx_daily_stats_date ON daily_member_statistics(statistics_date);
CREATE INDEX IF NOT EXISTS idx_daily_stats_openchat_date ON daily_member_statistics(openchat_id, statistics_date);

-- ==============================================================================
-- オプチャグラフが集計しているオープンチャットの成長ランキング（過去24時間・毎時更新）
-- ==============================================================================
CREATE TABLE IF NOT EXISTS growth_ranking_past_24_hours (
    ranking_position INTEGER PRIMARY KEY,   -- 順位（1位、2位...）
    openchat_id INTEGER NOT NULL,           -- オプチャグラフでオープンチャットを識別するための主キー（openchat_masterと紐づく）
    member_increase_count INTEGER NOT NULL, -- メンバー増加数
    growth_rate_percent REAL NOT NULL       -- 成長率（%）
);
CREATE INDEX IF NOT EXISTS idx_growth_24h_openchat ON growth_ranking_past_24_hours(openchat_id);

-- ==============================================================================
-- オプチャグラフが集計しているオープンチャットの成長ランキング（過去１時間・毎時更新）
-- ==============================================================================
CREATE TABLE IF NOT EXISTS growth_ranking_past_hour (
    ranking_position INTEGER PRIMARY KEY,   -- 順位（1位、2位...）
    openchat_id INTEGER NOT NULL,           -- オプチャグラフでオープンチャットを識別するための主キー（openchat_masterと紐づく）
    member_increase_count INTEGER NOT NULL, -- メンバー増加数
    growth_rate_percent REAL NOT NULL       -- 成長率（%）
);
CREATE INDEX IF NOT EXISTS idx_growth_hour_openchat ON growth_ranking_past_hour(openchat_id);

-- ==============================================================================
-- オプチャグラフが集計しているオープンチャットの成長ランキング（過去１週間・毎時更新）
-- ==============================================================================
CREATE TABLE IF NOT EXISTS growth_ranking_past_week (
    ranking_position INTEGER PRIMARY KEY,   -- 順位（1位、2位...）
    openchat_id INTEGER NOT NULL,           -- オプチャグラフでオープンチャットを識別するための主キー（openchat_masterと紐づく）
    member_increase_count INTEGER NOT NULL, -- メンバー増加数
    growth_rate_percent REAL NOT NULL       -- 成長率（%）
);
CREATE INDEX IF NOT EXISTS idx_growth_week_openchat ON growth_ranking_past_week(openchat_id);

-- ==============================================================================
-- LINEオープンチャット公式サイトの「ランキング」履歴（カテゴリ別・全体、1日1件、中央値保存）
-- ==============================================================================
CREATE TABLE IF NOT EXISTS line_official_activity_ranking_history (
    record_id INTEGER PRIMARY KEY,                -- レコードID
    openchat_id INTEGER NOT NULL,                 -- オプチャグラフでオープンチャットを識別するための主キー（openchat_masterと紐づく）
    category_id INTEGER NOT NULL,                 -- カテゴリID（0=すべて、1以上=各カテゴリ）（categoriesと紐づく）
    activity_ranking_position INTEGER NOT NULL,   -- その日のLINE公式「ランキング」順位（中央値、何件中何位かはline_official_ranking_total_countで確認）
    recorded_at TEXT NOT NULL,                    -- 記録日時（line_official_ranking_total_countと紐づく）
    record_date TEXT NOT NULL                     -- 記録日（ユニークキー用のカラム。取得不要）
);
CREATE INDEX IF NOT EXISTS idx_ranking_history_openchat ON line_official_activity_ranking_history(openchat_id);
CREATE INDEX IF NOT EXISTS idx_ranking_history_cat_date ON line_official_activity_ranking_history(category_id, record_date);
CREATE INDEX IF NOT EXISTS idx_ranking_history_recorded ON line_official_activity_ranking_history(recorded_at);

-- ==============================================================================
-- LINEオープンチャット公式サイトの「急上昇」履歴（カテゴリ別・全体、1日1件、最大値保存）
-- ==============================================================================
CREATE TABLE IF NOT EXISTS line_official_activity_trending_history (
    record_id INTEGER PRIMARY KEY,                -- レコードID
    openchat_id INTEGER NOT NULL,                 -- オプチャグラフでオープンチャットを識別するための主キー（openchat_masterと紐づく）
    category_id INTEGER NOT NULL,                 -- カテゴリID（0=すべて、1以上=各カテゴリ）（categoriesと紐づく）
    activity_trending_position INTEGER NOT NULL,  -- その日のLINE公式「急上昇」順位（最大値、何件中何位かはline_official_ranking_total_countで確認）
    recorded_at TEXT NOT NULL,                    -- 記録日時
    record_date TEXT NOT NULL                     -- 記録日
);
CREATE INDEX IF NOT EXISTS idx_trending_history_openchat ON line_official_activity_trending_history(openchat_id);
CREATE INDEX IF NOT EXISTS idx_trending_history_cat_date ON line_official_activity_trending_history(category_id, record_date);
CREATE INDEX IF NOT EXISTS idx_trending_history_recorded ON line_official_activity_trending_history(recorded_at);

-- ==============================================================================
-- LINEオープンチャット公式サイトの全ランキング総件数履歴（「ランキング」・「急上昇」、カテゴリ別・全体、毎時間記録）
-- ==============================================================================
CREATE TABLE IF NOT EXISTS line_official_ranking_total_count (
    record_id INTEGER PRIMARY KEY,                -- レコードID
    activity_trending_total_count INTEGER NOT NULL, -- その時間のLINE公式「急上昇」総件数（何件中何位かを知るために使用）
    activity_ranking_total_count INTEGER NOT NULL,  -- その時間のLINE公式「ランキング」総件数（何件中何位かを知るために使用）
    recorded_at TEXT NOT NULL,                      -- 記録日時（毎時間更新）
    category_id INTEGER NOT NULL                    -- カテゴリID（0=すべて、1以上=各カテゴリ）（categoriesと紐づく）
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_total_count_time_cat ON line_official_ranking_total_count(recorded_at, category_id);

-- ==============================================================================
-- オプチャグラフがLINEオープンチャット公式サイトから収集したオープンチャットマスターテーブル
-- ==============================================================================
CREATE TABLE IF NOT EXISTS openchat_master (
    openchat_id INTEGER PRIMARY KEY,        -- オプチャグラフでオープンチャットを識別するための主キー
    line_internal_id TEXT,                  -- LINE内部ID（emid）
    display_name TEXT NOT NULL,             -- オープンチャット名
    invitation_url TEXT,                    -- オープンチャット招待用URL（参加リンク）
    description TEXT,                       -- 説明
    profile_image_url TEXT,                 -- オープンチャットのメイン画像
    current_member_count INTEGER NOT NULL DEFAULT 0,  -- 現在のメンバー数
    verification_badge TEXT,                -- 認証バッジ（NULL:なし, 公式認証, スペシャル）
    category_id INTEGER,                    -- カテゴリID（categoriesと紐づく）
    join_method TEXT NOT NULL,              -- 参加方法（全体公開, 参加承認制, 参加コード入力制）
    established_at TEXT,                    -- オープンチャットの開設日時
    first_seen_at TEXT NOT NULL,            -- 初回取得日時
    last_updated_at TEXT NOT NULL           -- オープンチャット名、メイン画像、説明、認証バッジ、参加方法、カテゴリのいずれかが最後に更新された日時
);
CREATE INDEX IF NOT EXISTS idx_openchat_master_category ON openchat_master(category_id);
CREATE INDEX IF NOT EXISTS idx_openchat_master_updated ON openchat_master(last_updated_at);

-- ==============================================================================
-- 削除されたオープンチャット履歴
-- ==============================================================================
CREATE TABLE IF NOT EXISTS open_chat_deleted (
    id INTEGER PRIMARY KEY,                 -- オープンチャットID（openchat_master.openchat_idと紐づく）
    emid TEXT,                              -- LINE内部ID（emid）
    deleted_at TEXT NOT NULL                -- 削除日時
);
CREATE INDEX IF NOT EXISTS idx_deleted_date ON open_chat_deleted(deleted_at);

-- ==============================================================================
-- コメントテーブル
-- ==============================================================================
CREATE TABLE IF NOT EXISTS comment (
    comment_id INTEGER PRIMARY KEY,         -- コメントID
    open_chat_id INTEGER NOT NULL,          -- オープンチャットID（openchat_master.openchat_idと紐づく）
    id INTEGER NOT NULL,                    -- 旧ID（互換性のため保持）
    user_id TEXT NOT NULL,                  -- ユーザーID
    name TEXT NOT NULL,                     -- ユーザー名
    text TEXT NOT NULL,                     -- コメント本文
    time TEXT NOT NULL,                     -- 投稿日時
    flag INTEGER NOT NULL DEFAULT 0         -- フラグ（0: 通常、その他: 削除・非表示など）
);
CREATE INDEX IF NOT EXISTS idx_comment_open_chat ON comment(open_chat_id);
CREATE INDEX IF NOT EXISTS idx_comment_time ON comment(time);

-- ==============================================================================
-- いいねテーブル
-- ==============================================================================
CREATE TABLE IF NOT EXISTS comment_like (
    id INTEGER PRIMARY KEY,                 -- いいねID
    comment_id INTEGER NOT NULL,            -- コメントID（comment.comment_idと紐づく）
    user_id TEXT NOT NULL,                  -- ユーザーID
    type TEXT NOT NULL,                     -- いいねタイプ
    time TEXT NOT NULL                      -- いいね日時
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_like_unique ON comment_like(comment_id, user_id);
CREATE INDEX IF NOT EXISTS idx_like_comment ON comment_like(comment_id);

-- ==============================================================================
-- オープンチャット投稿禁止テーブル
-- ==============================================================================
CREATE TABLE IF NOT EXISTS ban_room (
    id INTEGER PRIMARY KEY,                 -- BAN ID
    open_chat_id INTEGER NOT NULL,          -- オープンチャットID（openchat_master.openchat_idと紐づく）
    created_at TEXT NOT NULL,               -- 作成日時
    type INTEGER NOT NULL DEFAULT 0         -- BANタイプ
);
CREATE INDEX IF NOT EXISTS idx_ban_room_open_chat ON ban_room(open_chat_id);

-- ==============================================================================
-- ユーザー投稿禁止テーブル
-- ==============================================================================
CREATE TABLE IF NOT EXISTS ban_user (
    id INTEGER PRIMARY KEY,                 -- BAN ID
    user_id TEXT NOT NULL,                  -- ユーザーID
    ip TEXT NOT NULL,                       -- IPアドレス
    created_at TEXT NOT NULL,               -- 作成日時
    type INTEGER NOT NULL DEFAULT 0         -- BANタイプ
);
CREATE INDEX IF NOT EXISTS idx_ban_user_user ON ban_user(user_id);
CREATE INDEX IF NOT EXISTS idx_ban_user_ip ON ban_user(ip);

-- ==============================================================================
-- ログテーブル
-- ==============================================================================
CREATE TABLE IF NOT EXISTS comment_log (
    id INTEGER PRIMARY KEY,                 -- ログID
    entity_id INTEGER NOT NULL,             -- エンティティID
    type TEXT NOT NULL,                     -- ログタイプ
    data TEXT NOT NULL,                     -- ログデータ
    ip TEXT NOT NULL,                       -- IPアドレス
    ua TEXT NOT NULL                        -- ユーザーエージェント
);
CREATE INDEX IF NOT EXISTS idx_log_entity ON comment_log(entity_id);
CREATE INDEX IF NOT EXISTS idx_log_type ON comment_log(type);
