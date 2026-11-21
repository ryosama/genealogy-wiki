<?php
declare(strict_types=1);

// Les codes correspondent aux champs de la fiche et à leurs ancres. « relations » désigne une fiche isolée.
const PERSON_TASK_LABELS = [
    'first_name' => 'Prénom', 'last_name' => 'Nom de famille', 'sex' => 'Sexe', 'residence' => 'Lieu de vie',
    'birth_date' => 'Date de naissance', 'birth_place' => 'Lieu de naissance',
    'death_date' => 'Date de décès', 'death_place' => 'Lieu de décès',
    'relations' => 'Relier cette personne à l’arbre',
];

function sync_person_task(PDO $db, int $personId, string $field, bool $missing): int
{
    if ($missing) {
        return query($db, 'INSERT INTO person_tasks (person_id, field) VALUES (?, ?) ON CONFLICT(person_id, field) DO NOTHING',
            [$personId, $field])->rowCount();
    }
    // Une décision d'ignorer le champ est conservée même si le champ est rempli puis laissé vide de nouveau.
    query($db, 'DELETE FROM person_tasks WHERE person_id = ? AND field = ? AND ignored = 0', [$personId, $field]);
    return 0;
}

function sync_person_tasks(PDO $db, array $person): int
{
    $added = 0;
    foreach (array_keys(PERSON_TASK_LABELS) as $field) {
        if ($field === 'relations') {
            continue;
        }
        $applicable = $person['deceased'] || !in_array($field, ['death_date', 'death_place'], true);
        $value = $person[$field] ?? '';
        $missing = $applicable && preg_match('/[^\s\p{Z}\x{200b}]/u', $value) !== 1;
        $added += sync_person_task($db, $person['id'], $field, $missing);
    }
    return $added;
}

function sync_isolation_tasks(PDO $db, int ...$personIds): void
{
    foreach (array_unique($personIds) as $id) {
        // Un lien de n'importe quel type suffit, que la fiche soit à gauche ou à droite de la relation.
        $linked = query($db, 'SELECT 1 FROM relations WHERE a_id = :id OR b_id = :id LIMIT 1', ['id' => $id])->fetchColumn();
        sync_person_task($db, $id, 'relations', $linked === false);
    }
}

function scan_person_tasks(PDO $db): array
{
    return write_transaction($db, static function (PDO $db): array {
        $report = ['people' => 0, 'isolated' => 0, 'added' => 0, 'pending' => 0];
        $people = $db->query(<<<'SQL'
            SELECT p.*, NOT EXISTS (SELECT 1 FROM relations r WHERE r.a_id = p.id OR r.b_id = p.id) AS isolated
            FROM people p ORDER BY p.id
            SQL);
        foreach ($people as $person) {
            $report['people']++;
            $report['isolated'] += (int) $person['isolated'];
            $report['added'] += sync_person_tasks($db, $person);
            $report['added'] += sync_person_task($db, $person['id'], 'relations', (bool) $person['isolated']);
        }
        $report['pending'] = (int) $db->query('SELECT COUNT(*) FROM person_tasks WHERE ignored = 0')->fetchColumn();
        return $report;
    });
}

function pending_person_tasks(PDO $db): array
{
    $rows = $db->query(<<<'SQL'
        SELECT p.id, p.first_name, p.last_name, p.sex, p.birth_date, p.death_date, t.field
        FROM person_tasks t JOIN people p ON p.id = t.person_id
        WHERE t.ignored = 0 ORDER BY p.last_name COLLATE NOCASE, p.first_name COLLATE NOCASE, p.id, t.rowid
        SQL);
    $people = [];
    foreach ($rows as $row) {
        $people[$row['id']] ??= [
            'id' => $row['id'], 'name' => full_name($row), 'sex' => $row['sex'],
            'birth_date' => $row['birth_date'], 'death_date' => $row['death_date'], 'fields' => [],
        ];
        $people[$row['id']]['fields'][] = $row['field'];
    }
    return $people;
}

function ignore_person_task(PDO $db, int $personId, string $field): void
{
    if (!isset(PERSON_TASK_LABELS[$field])) {
        throw new InputException('Cette tâche est introuvable.', 404);
    }
    query($db, 'UPDATE person_tasks SET ignored = 1 WHERE person_id = ? AND field = ?', [$personId, $field]);
}

function transfer_ignored_person_tasks(PDO $db, int $keepId, int $removeId): void
{
    query($db, <<<'SQL'
        INSERT INTO person_tasks (person_id, field, ignored)
        SELECT :keep, field, 1 FROM person_tasks WHERE person_id = :remove AND ignored = 1
        ON CONFLICT(person_id, field) DO UPDATE SET ignored = 1
        SQL, ['keep' => $keepId, 'remove' => $removeId]);
}
