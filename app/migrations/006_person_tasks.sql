-- Les informations de décès déjà enregistrées indiquent que la personne est décédée.
ALTER TABLE people ADD COLUMN deceased INTEGER NOT NULL DEFAULT 0 CHECK (deceased IN (0, 1));
UPDATE people SET deceased = 1 WHERE death_date <> '' OR death_year IS NOT NULL OR death_place <> '';

-- La clé unique rend le balayage répétable et conserve les décisions d'ignorer les tâches.
CREATE TABLE person_tasks (
    person_id INTEGER NOT NULL REFERENCES people(id) ON DELETE CASCADE,
    field TEXT NOT NULL,
    ignored INTEGER NOT NULL DEFAULT 0 CHECK (ignored IN (0, 1)),
    PRIMARY KEY (person_id, field)
);

PRAGMA user_version = 6;
