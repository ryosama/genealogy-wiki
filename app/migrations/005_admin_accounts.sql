-- Supprimer les rôles distincts. Les anciens éditeurs actifs attendent une validation avant de recevoir les droits d'administration.
CREATE TABLE users_v5 (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    email TEXT NOT NULL COLLATE NOCASE UNIQUE,
    password_hash TEXT,
    state TEXT NOT NULL CHECK (state IN ('pending', 'invited', 'active')),
    token_hash TEXT UNIQUE,
    token_expires INTEGER
);

INSERT INTO users_v5 (id, first_name, last_name, email, password_hash, state, token_hash, token_expires)
SELECT id, first_name, last_name, email, password_hash,
    CASE WHEN role = 'editor' AND state = 'active' THEN 'pending' ELSE state END,
    token_hash, token_expires
FROM users;

-- Un ancien identifiant de compte supprimé ne doit jamais être réattribué.
INSERT INTO sqlite_sequence (name, seq)
SELECT 'users_v5', seq FROM sqlite_sequence WHERE name = 'users'
    AND NOT EXISTS (SELECT 1 FROM sqlite_sequence WHERE name = 'users_v5');
UPDATE sqlite_sequence SET seq = MAX(seq, COALESCE((SELECT seq FROM sqlite_sequence WHERE name = 'users'), 0))
WHERE name = 'users_v5';

CREATE TABLE first_admin_v5 (
    slot INTEGER PRIMARY KEY CHECK (slot = 1),
    user_id INTEGER NOT NULL UNIQUE REFERENCES users_v5(id) ON DELETE RESTRICT
);
INSERT INTO first_admin_v5 (slot, user_id) SELECT slot, user_id FROM first_admin;

DROP TABLE first_admin;
DROP TABLE users;
ALTER TABLE users_v5 RENAME TO users;
ALTER TABLE first_admin_v5 RENAME TO first_admin;

-- Après une suppression volontaire, le prochain compte est protégé seulement si l'administration repart de zéro.
CREATE TRIGGER users_record_first_admin
AFTER INSERT ON users
WHEN NEW.state = 'active' AND NOT EXISTS (SELECT 1 FROM users WHERE state = 'active' AND id <> NEW.id)
BEGIN
    INSERT OR IGNORE INTO first_admin (slot, user_id) VALUES (1, NEW.id);
END;

PRAGMA user_version = 5;
