-- Un compte passe de pending à invited, puis à active. Seule l'empreinte du lien est conservée.
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

-- search_name sert à la recherche sans accents. Les dates gardent leur libellé et une année filtrable.
-- AUTOINCREMENT évite de réutiliser un identifiant déjà diffusé dans une URL.
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

-- a_id est le parent et b_id l'enfant dans une filiation. Pour une union, le plus petit identifiant vient en premier.
-- Les liens disparaissent avec la fiche. end_year renseigne la fin d'une union.
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

-- Les fichiers binaires sont stockés avec la fiche pour rester dans la même sauvegarde SQLite.
CREATE TABLE IF NOT EXISTS media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    person_id INTEGER NOT NULL REFERENCES people(id) ON DELETE CASCADE,
    kind TEXT NOT NULL CHECK (kind IN ('photo','document')),
    mime TEXT NOT NULL,
    title TEXT NOT NULL,
    bytes BLOB NOT NULL
);
CREATE INDEX IF NOT EXISTS media_by_person ON media (person_id, kind, id);

-- Garder le nom de la fiche et celui de l'éditeur après suppression de la fiche.
-- details contient les valeurs précédentes ou les précisions de l'action, en JSON.
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

-- Un compteur par action et adresse IP. bucket stocke la clé sous forme d'empreinte.
CREATE TABLE IF NOT EXISTS auth_limits (
    bucket TEXT PRIMARY KEY,
    expires_at INTEGER NOT NULL,
    attempts INTEGER NOT NULL
);

-- Version du schéma vérifiée au démarrage de l'application.
PRAGMA user_version = 1;
