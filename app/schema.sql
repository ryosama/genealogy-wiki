CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    email TEXT NOT NULL COLLATE NOCASE UNIQUE,
    password_hash TEXT,
    role TEXT NOT NULL CHECK (role IN ('admin', 'editor')),
    state TEXT NOT NULL CHECK (state IN ('pending', 'invited', 'active')),
    token_hash TEXT UNIQUE,
    token_expires INTEGER
);

CREATE TABLE IF NOT EXISTS people (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    search_name TEXT NOT NULL,
    birth_date TEXT NOT NULL,
    birth_year INTEGER,
    birth_place TEXT NOT NULL,
    death_date TEXT NOT NULL,
    death_year INTEGER,
    death_place TEXT NOT NULL,
    residence TEXT NOT NULL,
    biography TEXT NOT NULL,
    notable_facts TEXT NOT NULL
);

-- For a parent link, a_id is the parent. For a union, the lower ID comes first.
CREATE TABLE IF NOT EXISTS relations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    a_id INTEGER NOT NULL REFERENCES people(id) ON DELETE CASCADE,
    b_id INTEGER NOT NULL REFERENCES people(id) ON DELETE CASCADE,
    kind TEXT NOT NULL CHECK (kind IN ('biological','adoptive','marriage','union')),
    end_year INTEGER,
    CHECK (a_id <> b_id),
    CHECK (kind IN ('biological','adoptive') OR a_id < b_id),
    UNIQUE (a_id,b_id,kind)
);
CREATE INDEX IF NOT EXISTS relations_by_b ON relations (b_id);

CREATE TABLE IF NOT EXISTS media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    person_id INTEGER NOT NULL REFERENCES people(id) ON DELETE CASCADE,
    kind TEXT NOT NULL CHECK (kind IN ('photo','document')),
    mime TEXT NOT NULL,
    title TEXT NOT NULL,
    bytes BLOB NOT NULL
);
CREATE INDEX IF NOT EXISTS media_by_person ON media (person_id, kind, id);

-- Keep the historical record even after deletion and snapshot the editor's name.
CREATE TABLE IF NOT EXISTS history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    person_id INTEGER REFERENCES people(id) ON DELETE SET NULL,
    person_name TEXT NOT NULL,
    editor_name TEXT NOT NULL,
    action TEXT NOT NULL,
    details TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS history_by_person ON history (person_id, id DESC);

CREATE TABLE IF NOT EXISTS auth_limits (
    bucket TEXT PRIMARY KEY,
    expires_at INTEGER NOT NULL,
    attempts INTEGER NOT NULL
);

PRAGMA user_version = 1;
