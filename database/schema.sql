-- CST8265 MemeForge schema (SQLite)

DROP TABLE IF EXISTS memes;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE COLLATE NOCASE,
    email         TEXT NOT NULL,
    password_hash TEXT NOT NULL,              -- password_hash() output, never plaintext
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE memes (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER NOT NULL,
    title        TEXT NOT NULL,
    top_text     TEXT NOT NULL DEFAULT '',
    bottom_text  TEXT NOT NULL DEFAULT '',
    image_path   TEXT NOT NULL,               -- set by the server, never by the user
    image_source TEXT NOT NULL CHECK (image_source IN ('template', 'upload', 'url')),
    created_at   TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at   TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_memes_user ON memes(user_id);
CREATE INDEX idx_memes_created ON memes(created_at);
