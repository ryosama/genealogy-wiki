-- Les fiches de version 1 gardent un sexe non renseigné.
ALTER TABLE people ADD COLUMN sex TEXT CHECK (sex IN ('male', 'female'));

-- Recréer la contrainte des types de relation tout en conservant les identifiants.
ALTER TABLE relations RENAME TO relations_v1;
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
INSERT INTO relations (id, a_id, b_id, kind, end_year)
    SELECT id, a_id, b_id, kind, end_year FROM relations_v1;

-- Garder aussi le dernier identifiant attribué si les derniers liens ont été supprimés.
INSERT INTO sqlite_sequence (name, seq)
    SELECT 'relations', seq FROM sqlite_sequence
    WHERE name = 'relations_v1' AND NOT EXISTS (SELECT 1 FROM sqlite_sequence WHERE name = 'relations');
UPDATE sqlite_sequence
    SET seq = MAX(seq, COALESCE((SELECT seq FROM sqlite_sequence WHERE name = 'relations_v1'), 0))
    WHERE name = 'relations';
DROP TABLE relations_v1;
CREATE INDEX relations_by_b ON relations (b_id);

PRAGMA user_version = 2;
