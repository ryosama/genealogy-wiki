<?php
declare(strict_types=1);

const RELATION_LABELS = [
    'biological' => 'Filiation biologique',
    'adoptive' => 'Filiation adoptive',
    'marriage' => 'Mariage',
    'union' => 'Union',
];

const ACTION_LABELS = [
    'created' => 'Fiche créée', 'edited' => 'Fiche modifiée', 'deleted' => 'Fiche supprimée',
    'merged' => 'Fiches fusionnées', 'merged_away' => 'Fiche fusionnée',
    'linked' => 'Relation ajoutée', 'relinked' => 'Relation modifiée', 'unlinked' => 'Relation supprimée',
    'media_added' => 'Fichier ajouté', 'media_removed' => 'Fichier supprimé',
];

function find_person(PDO $db, int $id): array
{
    $person = query($db, 'SELECT * FROM people WHERE id = ?', [$id])->fetch();
    if (!$person) {
        throw new InputException('Cette personne est introuvable.', 404);
    }
    return $person;
}

function photo_subquery(string $personAlias): string
{
    return "(SELECT id FROM media WHERE kind = 'photo' AND person_id = $personAlias.id ORDER BY id LIMIT 1)";
}

function search_people(PDO $db, array $filters, int $page): array
{
    $terms = preg_split('/\s+/', normalized_name(mb_substr($filters['name'], 0, 150)), -1, PREG_SPLIT_NO_EMPTY);
    $where = [];
    $params = [];
    foreach ($terms as $term) {
        $where[] = 'p.search_name LIKE ?';
        $params[] = '%' . $term . '%';
    }
    if ($filters['year'] !== '') {
        if (!preg_match('/^\d{1,4}$/D', $filters['year']) || (int) $filters['year'] < 1) {
            throw new InputException('Indique une année valide entre 1 et 9999.');
        }
        $year = (int) $filters['year'];
        $where[] = match ($filters['when']) {
            'birth' => 'p.birth_year = ?',
            'death' => 'p.death_year = ?',
            default => '(p.birth_year = ? OR p.death_year = ?)',
        };
        $params[] = $year;
        if (!in_array($filters['when'], ['birth', 'death'], true)) {
            $params[] = $year;
        }
    }
    $filter = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $total = (int) query($db, 'SELECT COUNT(*) FROM people p' . $filter, $params)->fetchColumn();
    $perPage = 24;
    $page = min($page, max(1, (int) ceil($total / $perPage)));
    $offset = ($page - 1) * $perPage;
    $statement = 'SELECT p.*, ' . photo_subquery('p') . ' AS photo_id FROM people p' . $filter
        . " ORDER BY p.last_name COLLATE NOCASE, p.first_name COLLATE NOCASE, p.id LIMIT $perPage OFFSET $offset";
    return ['people' => query($db, $statement, $params)->fetchAll(), 'total' => $total,
        'page' => $page, 'pages' => (int) ceil($total / $perPage)];
}

function stats(PDO $db): array
{
    // Sort the parent IDs before grouping so children of the same parents count once.
    return $db->query(<<<'SQL'
        SELECT
            (SELECT COUNT(*) FROM people) AS people,
            (SELECT COUNT(*) FROM media WHERE kind = 'photo') AS photos,
            (SELECT COUNT(DISTINCT parents) FROM (
                SELECT group_concat(a_id) AS parents FROM (
                    SELECT DISTINCT a_id, b_id FROM relations
                    WHERE kind IN ('biological','adoptive') ORDER BY b_id, a_id
                ) GROUP BY b_id
            )) AS families
        SQL)->fetch();
}

function recent_people(PDO $db): array
{
    return $db->query('SELECT p.*, h.editor_name, h.action, h.created_at, ' . photo_subquery('p') . ' AS photo_id'
        . ' FROM history h JOIN people p ON p.id = h.person_id'
        . ' WHERE h.id = (SELECT MAX(id) FROM history WHERE person_id = p.id)'
        . ' ORDER BY h.id DESC LIMIT 6')->fetchAll();
}

function record_change(PDO $db, array $person, string $editor, string $action, ?array $details = null): void
{
    query($db, 'INSERT INTO history (person_id, person_name, editor_name, action, details) VALUES (?,?,?,?,?)', [
        $person['id'], full_name($person), $editor, $action, $details ? json_data($details) : null,
    ]);
}

function save_person(PDO $db, int $id, array $fields, string $editor): int
{
    return write_transaction($db, static function (PDO $db) use ($id, $fields, $editor): int {
        if ($id === 0) {
            $columns = implode(', ', array_keys($fields));
            $values = ':' . implode(', :', array_keys($fields));
            query($db, "INSERT INTO people ($columns) VALUES ($values)", $fields);
            $newId = (int) $db->lastInsertId();
            record_change($db, $fields + ['id' => $newId], $editor, 'created');
            return $newId;
        }
        $original = find_person($db, $id);
        $changes = array_filter($fields, static fn ($value, $key) => $value !== $original[$key], ARRAY_FILTER_USE_BOTH);
        if ($changes) {
            $assignments = array_map(static fn ($key) => "$key = :$key", array_keys($changes));
            query($db, 'UPDATE people SET ' . implode(', ', $assignments) . ' WHERE id = :id', $changes + ['id' => $id]);
            record_change($db, $fields + ['id' => $id], $editor, 'edited', array_intersect_key($original, $changes));
        }
        return $id;
    });
}

function is_parent_kind(string $kind): bool
{
    return in_array($kind, ['biological', 'adoptive'], true);
}

function relation_fields(int $a, int $b, string $kind, ?int $endYear): array
{
    if (!isset(RELATION_LABELS[$kind]) || !$a || !$b || $a === $b) {
        throw new InputException('Sélectionne deux personnes différentes et un type de relation valide.');
    }
    if (!is_parent_kind($kind)) {
        [$a, $b] = [min($a, $b), max($a, $b)];
    }
    return ['a_id' => $a, 'b_id' => $b, 'kind' => $kind, 'end_year' => is_parent_kind($kind) ? null : $endYear];
}

function assert_parent_graph(PDO $db, array $relation, int $ignoreId = 0): void
{
    if (!is_parent_kind($relation['kind'])) {
        return;
    }
    $cycle = query($db, <<<'SQL'
        WITH RECURSIVE descendants(id) AS (
            SELECT ?
            UNION
            SELECT r.b_id FROM relations r JOIN descendants d ON d.id = r.a_id
            WHERE r.kind IN ('biological','adoptive') AND r.id <> ?
        )
        SELECT 1 FROM descendants WHERE id = ?
        SQL, [$relation['b_id'], $ignoreId, $relation['a_id']])->fetchColumn();
    if ($cycle) {
        throw new InputException('Cette filiation créerait une boucle dans les générations.');
    }
}

function relation_exists(PDO $db, array $relation, int $ignoreId = 0): array|false
{
    return query($db, 'SELECT * FROM relations WHERE a_id = ? AND b_id = ? AND kind = ? AND id <> ?',
        [$relation['a_id'], $relation['b_id'], $relation['kind'], $ignoreId])->fetch();
}

function insert_relation(PDO $db, array $relation): int
{
    query($db, 'INSERT INTO relations (a_id,b_id,kind,end_year) VALUES (:a_id,:b_id,:kind,:end_year)', $relation);
    return (int) $db->lastInsertId();
}

function find_relation(PDO $db, int $id): array
{
    $relation = query($db, 'SELECT * FROM relations WHERE id = ?', [$id])->fetch();
    if (!$relation) {
        throw new InputException('Cette relation est introuvable.', 404);
    }
    return $relation;
}

function log_relation(PDO $db, array $people, array $relation, string $editor, string $action, ?array $before = null): void
{
    $currentEnds = [$relation['a_id'], $relation['b_id']];
    $previousEnds = $before ? [$before['a_id'], $before['b_id']] : [];
    foreach (array_unique(array_merge($currentEnds, $previousEnds)) as $id) {
        $ends = in_array($id, $currentEnds, true) ? $currentEnds : $previousEnds;
        $other = $ends[0] === $id ? $ends[1] : $ends[0];
        record_change($db, $people[$id], $editor, $action, [
            'Relation' => RELATION_LABELS[$relation['kind']],
            'Personne liée' => full_name($people[$other]),
            'Relation précédente' => $before ? RELATION_LABELS[$before['kind']] . ' · '
                . full_name($people[$before['a_id']]) . ' / ' . full_name($people[$before['b_id']]) : null,
        ]);
    }
}

function save_relation(PDO $db, int $id, array $relation, string $editor): int
{
    return write_transaction($db, static function (PDO $db) use ($id, $relation, $editor): int {
        $old = $id ? find_relation($db, $id) : null;
        $people = [];
        foreach (array_unique(array_merge([$relation['a_id'], $relation['b_id']], $old ? [$old['a_id'], $old['b_id']] : [])) as $personId) {
            $people[$personId] = find_person($db, $personId);
        }
        if (relation_exists($db, $relation, $id)) {
            throw new InputException('Cette relation existe déjà.');
        }
        assert_parent_graph($db, $relation, $id);
        if (!$id) {
            $id = insert_relation($db, $relation);
            log_relation($db, $people, $relation, $editor, 'linked');
        } elseif (array_intersect_key($old, $relation) != $relation) {
            query($db, 'UPDATE relations SET a_id = :a_id, b_id = :b_id, kind = :kind, end_year = :end_year WHERE id = :id',
                $relation + ['id' => $id]);
            log_relation($db, $people, $relation, $editor, 'relinked', $old);
        }
        return $id;
    });
}

function delete_relation(PDO $db, int $id, string $editor): void
{
    write_transaction($db, static function (PDO $db) use ($id, $editor): void {
        $relation = find_relation($db, $id);
        $people = [
            $relation['a_id'] => find_person($db, $relation['a_id']),
            $relation['b_id'] => find_person($db, $relation['b_id']),
        ];
        query($db, 'DELETE FROM relations WHERE id = ?', [$id]);
        log_relation($db, $people, $relation, $editor, 'unlinked');
    });
}

function person_relations(PDO $db, int $id): array
{
    $relations = query($db, <<<'SQL'
        SELECT p.id, p.first_name, p.last_name, r.id AS relation_id, r.kind, r.end_year,
            CASE WHEN r.kind IN ('marriage','union') THEN 'partners'
                 WHEN r.b_id = :id THEN 'parents' ELSE 'children' END AS relation_group
        FROM relations r JOIN people p ON p.id = CASE WHEN r.a_id = :id THEN r.b_id ELSE r.a_id END
        WHERE r.a_id = :id OR r.b_id = :id ORDER BY p.birth_year, p.last_name, p.first_name
        SQL, ['id' => $id])->fetchAll();
    $siblings = query($db, <<<'SQL'
        SELECT DISTINCT p.id, p.first_name, p.last_name, 'siblings' AS relation_group
        FROM relations a JOIN relations b ON a.a_id = b.a_id JOIN people p ON p.id = b.b_id
        WHERE a.b_id = :id AND b.b_id <> :id AND a.kind IN ('biological','adoptive') AND b.kind IN ('biological','adoptive')
        ORDER BY p.birth_year, p.last_name, p.first_name
        SQL, ['id' => $id])->fetchAll();
    $groups = ['parents' => [], 'partners' => [], 'children' => [], 'siblings' => []];
    foreach (array_merge($relations, $siblings) as $relation) {
        $groups[$relation['relation_group']][] = $relation;
    }
    return $groups;
}

function default_center(PDO $db): int
{
    return (int) $db->query(<<<'SQL'
        SELECT p.id
        FROM people p LEFT JOIN relations r ON r.a_id = p.id OR r.b_id = p.id
        GROUP BY p.id
        ORDER BY COUNT(r.id) DESC,
            COUNT(CASE WHEN r.b_id = p.id AND r.kind IN ('biological','adoptive') THEN 1 END) DESC, p.id
        LIMIT 1
        SQL)->fetchColumn();
}

function graph_data(PDO $db): array
{
    $people = $db->query('SELECT id, first_name, last_name, birth_date, birth_year, death_date, death_year FROM people ORDER BY id')->fetchAll();
    foreach ($people as &$person) {
        $person = ['id' => $person['id'], 'name' => full_name($person), 'dates' => lifespan($person), 'year' => $person['birth_year'],
            'initials' => initials($person), 'search' => normalized_name(full_name($person))];
    }
    unset($person);

    return ['people' => $people, 'relations' => $db->query('SELECT * FROM relations ORDER BY id')->fetchAll()];
}

function remove_person(PDO $db, int $id, string $editor): void
{
    write_transaction($db, static function (PDO $db) use ($id, $editor): void {
        $person = find_person($db, $id);
        foreach (query($db, 'SELECT * FROM relations WHERE a_id = :id OR b_id = :id', ['id' => $id])->fetchAll() as $relation) {
            $other = find_person($db, $relation['a_id'] === $id ? $relation['b_id'] : $relation['a_id']);
            record_change($db, $other, $editor, 'unlinked', ['Personne liée' => full_name($person)]);
        }
        record_change($db, $person, $editor, 'deleted', $person);
        query($db, 'DELETE FROM people WHERE id = ?', [$id]);
    });
}

function merge_people(PDO $db, int $keepId, int $removeId, string $editor): void
{
    if ($keepId === $removeId) {
        throw new InputException('Sélectionne une autre fiche à fusionner.');
    }
    write_transaction($db, static function (PDO $db) use ($keepId, $removeId, $editor): void {
        $keep = find_person($db, $keepId);
        $remove = find_person($db, $removeId);
        $links = query($db, 'SELECT * FROM relations WHERE a_id = :id OR b_id = :id', ['id' => $removeId])->fetchAll();
        query($db, 'DELETE FROM relations WHERE a_id = :id OR b_id = :id', ['id' => $removeId]);
        foreach ($links as $link) {
            $a = $link['a_id'] === $removeId ? $keepId : $link['a_id'];
            $b = $link['b_id'] === $removeId ? $keepId : $link['b_id'];
            if ($a === $b) {
                continue;
            }
            $newLink = relation_fields($a, $b, $link['kind'], $link['end_year']);
            $existingLink = relation_exists($db, $newLink);
            if ($existingLink) {
                if ($existingLink['end_year'] === null && $newLink['end_year'] !== null) {
                    query($db, 'UPDATE relations SET end_year = ? WHERE id = ?', [$newLink['end_year'], $existingLink['id']]);
                }
                continue;
            }
            assert_parent_graph($db, $newLink);
            insert_relation($db, $newLink);
            $other = find_person($db, $a === $keepId ? $b : $a);
            record_change($db, $other, $editor, 'relinked', ['Personne liée' => full_name($keep)]);
        }

        // Keep filled fields from the main profile and move all media and previous edits.
        $assignments = [];
        $fill = [];
        foreach (['birth_date', 'death_date', 'birth_place', 'death_place', 'residence', 'biography', 'notable_facts'] as $field) {
            if ($keep[$field] === '' && $remove[$field] !== '') {
                $fill[$field] = $remove[$field];
                $year = str_replace('_date', '_year', $field);
                if ($year !== $field) {
                    $fill[$year] = $remove[$year];
                }
            }
        }
        foreach ($fill as $key => $value) {
            $assignments[] = "$key = :$key";
        }
        if ($assignments) {
            query($db, 'UPDATE people SET ' . implode(', ', $assignments) . ' WHERE id = :id', $fill + ['id' => $keepId]);
        }
        query($db, 'UPDATE media SET person_id = ? WHERE person_id = ?', [$keepId, $removeId]);
        query($db, 'UPDATE history SET person_id = ? WHERE person_id = ?', [$keepId, $removeId]);
        record_change($db, $keep, $editor, 'merged', ['Fiche fusionnée' => full_name($remove)] + $remove);
        query($db, 'DELETE FROM people WHERE id = ?', [$removeId]);
    });
}

function history_labels(): array
{
    return ['first_name' => 'Prénom', 'last_name' => 'Nom', 'birth_date' => 'Naissance',
        'death_date' => 'Décès', 'birth_place' => 'Lieu de naissance', 'death_place' => 'Lieu de décès',
        'residence' => 'Lieu de vie', 'biography' => 'Biographie', 'notable_facts' => 'Faits notables',
        'Relation' => 'Relation', 'Relation précédente' => 'Relation précédente', 'Personne liée' => 'Personne liée',
        'Fiche fusionnée' => 'Fiche fusionnée', 'Fichier' => 'Fichier'];
}
