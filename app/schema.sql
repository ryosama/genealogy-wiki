-- Schéma complet d'une base neuve. L'application l'exécute dans une transaction.

-- Seuls les comptes actifs ont accès à l'administration. Une ancienne invitation sert à définir un mot de passe.
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    email TEXT NOT NULL COLLATE NOCASE UNIQUE,
    password_hash TEXT,
    state TEXT NOT NULL CHECK (state IN ('pending', 'invited', 'active')),
    token_hash TEXT UNIQUE,
    token_expires INTEGER
);

-- La clé étrangère protège le premier administrateur. Lui seul peut lever la protection pour se supprimer.
CREATE TABLE first_admin (
    slot INTEGER PRIMARY KEY CHECK (slot = 1),
    user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TRIGGER users_record_first_admin
AFTER INSERT ON users
WHEN NEW.state = 'active' AND NOT EXISTS (SELECT 1 FROM users WHERE state = 'active' AND id <> NEW.id)
BEGIN
    INSERT OR IGNORE INTO first_admin (slot, user_id) VALUES (1, NEW.id);
END;

-- search_name sert à la recherche sans accents. Les dates gardent leur libellé et une année filtrable.
-- AUTOINCREMENT évite de réutiliser un identifiant déjà diffusé dans une URL.
CREATE TABLE people (
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
    notable_facts TEXT NOT NULL,
    sex TEXT CHECK (sex IN ('male', 'female')),
    deceased INTEGER NOT NULL DEFAULT 0 CHECK (deceased IN (0, 1))
);

-- Une tâche ignorée reste mémorisée pour que les prochains balayages ne la recréent pas.
CREATE TABLE person_tasks (
    person_id INTEGER NOT NULL REFERENCES people(id) ON DELETE CASCADE,
    field TEXT NOT NULL,
    ignored INTEGER NOT NULL DEFAULT 0 CHECK (ignored IN (0, 1)),
    PRIMARY KEY (person_id, field)
);

-- a_id est le parent et b_id l'enfant dans une filiation. Pour une union ou une fratrie, le plus petit identifiant vient en premier.
-- Les liens disparaissent avec la fiche. end_year renseigne la fin d'une union.
CREATE TABLE relations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    a_id INTEGER NOT NULL REFERENCES people(id) ON DELETE CASCADE,
    b_id INTEGER NOT NULL REFERENCES people(id) ON DELETE CASCADE,
    kind TEXT NOT NULL CHECK (kind IN ('biological','adoptive','marriage','union','sibling')),
    end_year INTEGER,
    CHECK (a_id <> b_id),
    CHECK (kind IN ('biological','adoptive') OR a_id < b_id),
    UNIQUE (a_id,b_id,kind)
);
CREATE INDEX relations_by_b ON relations (b_id);

-- Les fichiers binaires sont stockés avec la fiche pour rester dans la même sauvegarde SQLite.
CREATE TABLE media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    person_id INTEGER NOT NULL REFERENCES people(id) ON DELETE CASCADE,
    kind TEXT NOT NULL CHECK (kind IN ('photo','document')),
    mime TEXT NOT NULL,
    title TEXT NOT NULL,
    bytes BLOB NOT NULL
);
CREATE INDEX media_by_person ON media (person_id, kind, id);

-- Chaque fiche peut utiliser le cadrage d'une de ses photos comme médaillon.
-- La photo source reste disponible en entier. La suppression d'une fiche ou de la photo retire aussi le médaillon.
CREATE TABLE portraits (
    person_id INTEGER PRIMARY KEY REFERENCES people(id) ON DELETE CASCADE,
    media_id INTEGER NOT NULL UNIQUE REFERENCES media(id) ON DELETE CASCADE,
    crop_x INTEGER NOT NULL CHECK (crop_x >= 0),
    crop_y INTEGER NOT NULL CHECK (crop_y >= 0),
    crop_size INTEGER NOT NULL CHECK (crop_size > 0),
    bytes BLOB NOT NULL
);

-- Garder le nom de la fiche et celui de l'éditeur après suppression de la fiche.
-- details contient les valeurs précédentes ou les précisions de l'action, en JSON.
CREATE TABLE history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    person_id INTEGER REFERENCES people(id) ON DELETE SET NULL,
    person_name TEXT NOT NULL,
    editor_name TEXT NOT NULL,
    action TEXT NOT NULL,
    details TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX history_by_person ON history (person_id, id DESC);

-- Un compteur par action et adresse IP. bucket stocke la clé sous forme d'empreinte.
CREATE TABLE auth_limits (
    bucket TEXT PRIMARY KEY,
    expires_at INTEGER NOT NULL,
    attempts INTEGER NOT NULL
);

-- Version du schéma vérifiée au démarrage de l'application.
PRAGMA user_version = 6;
