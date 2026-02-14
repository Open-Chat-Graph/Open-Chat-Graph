CREATE TABLE IF NOT EXISTS "ranking_position_ohlc" (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	open_chat_id INTEGER NOT NULL,
	category INTEGER NOT NULL,
	type TEXT NOT NULL,
	open_position INTEGER NOT NULL,
	high_position INTEGER NOT NULL,
	low_position INTEGER,
	close_position INTEGER NOT NULL,
	date TEXT NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS ranking_position_ohlc_IDX ON "ranking_position_ohlc" (open_chat_id, category, type, date);
