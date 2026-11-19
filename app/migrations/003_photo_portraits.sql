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

PRAGMA user_version = 3;
