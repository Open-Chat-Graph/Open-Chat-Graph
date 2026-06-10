-- SQLite schema for oc_page_cache database
-- ルーム個別ページの「分析文(narrative)」「関連ルーム(recommend/similarSize)」を
-- 事前計算したレンダリング済みHTML断片で保持する（部屋単位）。
-- /oc 表示時はこのテーブルをPK一発SELECTで読むだけにし、bot クロール時に
-- narrative の重い読み取りや recommend/similarSize の MySQL を発生させない。

CREATE TABLE IF NOT EXISTS oc_page_cache (
    open_chat_id INTEGER PRIMARY KEY,        -- オープンチャットID
    narrative_html TEXT NOT NULL DEFAULT '', -- 分析文セクションのHTML（空=データ無し）
    recommend_html TEXT NOT NULL DEFAULT '', -- 関連ルーム(similarSize優先・無ければおすすめ)のHTML
    updated_at TEXT NOT NULL                 -- 生成時刻 Y-m-d H:i:s
);
