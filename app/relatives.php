<?php
declare(strict_types=1);

// Les raccourcis fixent le type de lien et le sens de la filiation. Le sexe du conjoint reste modifiable.
const RELATIVE_PRESETS = [
    'partners' => [
        'partner' => ['title' => 'Créer un conjoint', 'kind' => 'union'],
        'spouse' => ['title' => 'Créer un mari ou une femme', 'kind' => 'marriage'],
    ],
    'parents' => [
        'father' => ['title' => 'Créer un père', 'sex' => 'male', 'kind' => 'biological'],
        'mother' => ['title' => 'Créer une mère', 'sex' => 'female', 'kind' => 'biological'],
    ],
    'children' => [
        'son' => ['title' => 'Créer un fils biologique', 'sex' => 'male', 'kind' => 'biological'],
        'daughter' => ['title' => 'Créer une fille biologique', 'sex' => 'female', 'kind' => 'biological'],
        'adopted-son' => ['title' => 'Créer un fils adoptif', 'sex' => 'male', 'kind' => 'adoptive'],
        'adopted-daughter' => ['title' => 'Créer une fille adoptive', 'sex' => 'female', 'kind' => 'adoptive'],
    ],
    'siblings' => [
        'brother' => ['title' => 'Créer un frère', 'sex' => 'male', 'kind' => 'sibling'],
        'sister' => ['title' => 'Créer une sœur', 'sex' => 'female', 'kind' => 'sibling'],
    ],
];

function create_relative(PDO $db, int $referenceId, string $presetId, array $source, string $editor): void
{
    foreach (RELATIVE_PRESETS as $group => $presets) {
        if (!isset($presets[$presetId])) {
            continue;
        }
        $preset = $presets[$presetId];
        write_transaction($db, static function (PDO $db) use ($referenceId, $group, $preset, $source, $editor): void {
            $reference = find_person($db, $referenceId);
            // Un conjoint prend par défaut le sexe opposé à celui de la personne de référence.
            $sex = $preset['sex'] ?? match ($reference['sex']) {
                'male' => 'female',
                'female' => 'male',
                default => '',
            };
            if ($group === 'partners' && array_key_exists('sex', $source)) {
                // Une valeur vide envoyée par le formulaire conserve le choix « Non renseigné ».
                $sex = input($source, 'sex');
            }
            $fields = person_fields([
                'first_name' => input($source, 'first_name'),
                'last_name' => input($source, 'last_name'),
                'sex' => $sex,
            ]);
            $newId = insert_person($db, $fields, $editor);
            $relative = $fields + ['id' => $newId];

            $parents = $group === 'siblings' ? query($db, <<<'SQL'
                SELECT a_id, kind FROM relations WHERE b_id = ? AND kind IN ('biological','adoptive')
                SQL, [$referenceId])->fetchAll() : [];
            if ($parents) {
                // Reprendre les filiations connues pour faire apparaître la nouvelle personne
                // dans la fratrie déduite. Sans parents connus, conserver le lien direct de fratrie.
                foreach ($parents as $parent) {
                    link_new_relative($db, find_person($db, $parent['a_id']), $relative, $parent['kind'], $editor);
                }
                record_change($db, $reference, $editor, 'linked', [
                    'Relation' => RELATION_LABELS['sibling'], 'Personne liée' => full_name($relative),
                ]);
            } elseif ($group === 'parents') {
                link_new_relative($db, $relative, $reference, $preset['kind'], $editor);
            } else {
                $relation = link_new_relative($db, $reference, $relative, $preset['kind'], $editor);
                if ($group === 'children') {
                    add_partner_filiation($db, $relation, $relative, $editor);
                }
            }
        });
        return;
    }
    throw new InputException('Choisis un type de proche valide.');
}

// Le nouveau sommet vient d'être créé dans la transaction. Aucun trajet existant ne peut former une boucle.
function link_new_relative(PDO $db, array $first, array $second, string $kind, string $editor): array
{
    $relation = relation_fields($first['id'], $second['id'], $kind, null);
    insert_relation($db, $relation);
    log_relation($db, [$first['id'] => $first, $second['id'] => $second], $relation, $editor, 'linked');
    return $relation;
}
