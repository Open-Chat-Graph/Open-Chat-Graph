-- SQLite schema for oc_page_cache database
-- ルーム個別ページの「分析文(narrative)」の事前計算データを保持する（部屋単位）。
-- キャッシュに入れるのは分析の「データ」(JSON)のみ。HTMLは保存しない——
-- レンダリング(テンプレート・url() 等)はリクエスト時に行う。
-- /oc 表示時はこのテーブルをPK一発SELECTで読むだけにし、bot クロール時に
-- narrative の重い統計読み取りを発生させない。

CREATE TABLE IF NOT EXISTS oc_page_cache (
    open_chat_id INTEGER PRIMARY KEY,        -- オープンチャットID
    narrative_data TEXT NOT NULL DEFAULT '', -- 分析データJSON {summary,detail,meta_description,pattern}（空=データ無し）
    narrative_html TEXT NOT NULL DEFAULT '', -- [旧形式・廃止] 事前レンダリングHTML。再生成で空になる
    recommend_html TEXT NOT NULL DEFAULT '', -- [旧形式・廃止] 関連ルームHTML。関連ルームはリクエスト時組み立てに移行済み
    updated_at TEXT NOT NULL                 -- 生成時刻 Y-m-d H:i:s
);
