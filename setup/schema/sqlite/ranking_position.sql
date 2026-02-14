CREATE TABLE rising (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	open_chat_id INTEGER NOT NULL,
	category INTEGER NOT NULL,
	"position" INTEGER NOT NULL,
	time TEXT NOT NULL
, date INTEGER DEFAULT ('2024-01-01') NOT NULL);
CREATE TABLE ranking (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	open_chat_id INTEGER NOT NULL,
	category INTEGER NOT NULL,
	"position" INTEGER NOT NULL,
	time TEXT NOT NULL
, date INTEGER DEFAULT ('2024-01-01') NOT NULL);
CREATE TABLE total_count (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	total_count_rising INTEGER NOT NULL,
	total_count_ranking INTEGER NOT NULL,
	time TEXT NOT NULL
, category INTEGER NOT NULL);
CREATE UNIQUE INDEX total_count_time_IDX ON total_count (time,category);
CREATE TABLE member (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	open_chat_id INTEGER NOT NULL,
	member INTEGER NOT NULL,
	time TEXT NOT NULL
);
CREATE UNIQUE INDEX ranking_open_chat_id_IDX2 ON ranking (open_chat_id,category,date);
CREATE UNIQUE INDEX rising_open_chat_id_IDX ON rising (open_chat_id,category,date);
