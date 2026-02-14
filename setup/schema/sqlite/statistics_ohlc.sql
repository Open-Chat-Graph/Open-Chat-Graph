CREATE TABLE IF NOT EXISTS "statistics_ohlc" (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	open_chat_id INTEGER NOT NULL,
	open_member INTEGER NOT NULL,
	high_member INTEGER NOT NULL,
	low_member INTEGER NOT NULL,
	close_member INTEGER NOT NULL,
	date TEXT NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS statistics_ohlc_open_chat_id_IDX ON "statistics_ohlc" (open_chat_id, date);
