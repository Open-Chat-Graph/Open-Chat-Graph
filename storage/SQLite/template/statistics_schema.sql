CREATE TABLE IF NOT EXISTS "statistics" (
	id  INTEGER PRIMARY KEY AUTOINCREMENT,
	open_chat_id INTEGER NOT NULL,
	"member" INTEGER NOT NULL,
	date TEXT NOT NULL
);
CREATE UNIQUE INDEX statistics2_open_chat_id_IDX ON "statistics" (open_chat_id,date);
