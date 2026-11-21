<?php
declare(strict_types=1);

// Prépare le nom du gabarit, le titre et les données spécifiques à la page.
function page_data(PDO $db, string $route, int $id, ?array $user, array $common): array
{
    switch ($route) {
        case '':
            return ['home', 'Accueil', [
                'stats' => stats($db), 'recent' => recent_people($db), 'center' => default_center($db),
            ]];

        case 'personnes':
            $filters = people_search_inputs($_GET);
            try {
                $results = search_people($db, $filters, max(1, id_value($_GET['page'] ?? null)));
            } catch (InputException $error) {
                $results = ['people' => [], 'total' => 0, 'page' => 1, 'pages' => 0, 'error' => $error->getMessage()];
            }
            return ['directory', 'Personnes', $results + ['filters' => $filters]];

        case 'personne':
            // La même fiche sert à la consultation, à l'édition et à la création.
            $person = $id ? find_person($db, $id) : [];
            $values = $person;
            if ($user && $common['error'] && input($_POST, 'action') === 'save-person') {
                // Conserver la saisie à corriger et nettoyer le HTML avant de le réafficher.
                foreach (['first_name', 'last_name', 'sex', 'birth_date', 'death_date', 'birth_place', 'death_place', 'residence',
                    'biography', 'notable_facts'] as $field) {
                    $value = input($_POST, $field);
                    $values[$field] = in_array($field, ['biography', 'notable_facts'], true)
                        ? bio_html(mb_strcut($value, 0, 70000)) : $value;
                }
            }
            if (!$id) {
                require_admin($user);
                return ['person', 'Nouvelle personne', ['person' => $person, 'values' => $values]];
            }

            $person['portrait_id'] = (int) query($db, 'SELECT media_id FROM portraits WHERE person_id = ?', [$id])->fetchColumn();
            // Les listes utilisent les métadonnées. Le navigateur charge chaque fichier à son URL.
            $media = query($db, 'SELECT id,kind,mime,title FROM media WHERE person_id = ? ORDER BY id', [$id])->fetchAll();
            $photos = array_values(array_filter($media, static fn ($item) => $item['kind'] === 'photo'));
            $documents = array_values(array_filter($media, static fn ($item) => $item['kind'] === 'document'));
            $duplicates = $user
                ? query($db, 'SELECT id,first_name,last_name,birth_year FROM people WHERE id <> ? ORDER BY search_name', [$id])->fetchAll()
                : [];
            return ['person', full_name($person), [
                'person' => $person, 'values' => $values, 'photos' => $photos, 'documents' => $documents, 'duplicates' => $duplicates,
                'groups' => person_relations($db, $id), 'graph' => graph_data($db), 'center' => $id,
                'attemptedRelation' => attempted_relation($common), 'attemptedRelative' => attempted_relative($common),
            ]];

        case 'arbre':
            $center = $id ?: default_center($db);
            $person = $center ? find_person($db, $center) : null;
            return ['tree', 'Arbre généalogique', [
                'person' => $person, 'graph' => graph_data($db), 'center' => $center,
                'attemptedRelation' => attempted_relation($common), 'attemptedRelative' => attempted_relative($common),
            ]];

        case 'medaillon':
            require_admin($user);
            $person = find_person($db, $id);
            $editor = portrait_editor_data($db, $id, id_value($_GET['photo'] ?? null));
            return ['portrait', $editor['saved'] ? 'Ajuster le médaillon' : 'Créer le médaillon', $editor + ['person' => $person]];

        case 'historique':
            require_admin($user);
            $person = $id ? find_person($db, $id) : null;
            $base = 'SELECT * FROM history' . ($id ? ' WHERE person_id = :id' : '');
            $count = (int) query($db, 'SELECT COUNT(*) FROM history' . ($id ? ' WHERE person_id = :id' : ''),
                $id ? ['id' => $id] : [])->fetchColumn();
            $page = min(max(1, id_value($_GET['page'] ?? null)), max(1, (int) ceil($count / 25)));
            $offset = ($page - 1) * 25;
            // La 26e entrée indique qu'une page suivante existe. La page en affiche 25.
            $records = query($db, $base . ' ORDER BY id DESC LIMIT 26 OFFSET ' . $offset, $id ? ['id' => $id] : [])->fetchAll();
            return ['history', 'Historique des modifications', [
                'person' => $person, 'id' => $id, 'records' => array_slice($records, 0, 25), 'page' => $page, 'more' => count($records) > 25,
            ]];

        case 'connexion':
            return ['account', 'Se connecter', ['mode' => 'login']];

        case 'inscription':
            return ['account', 'Demander un compte administrateur', ['mode' => 'request', 'challenge' => registration_challenge()]];

        case 'activation':
            $token = input($_GET, 'token');
            return ['account', 'Choisir un mot de passe', [
                'mode' => 'activate', 'token' => $token, 'account' => activation_account($db, $token),
            ]];

        case 'comptes':
            require_admin($user);
            return ['accounts', 'Gestion des comptes', ['accounts' => managed_accounts($db), 'firstAdmin' => first_admin_id($db)]];

        case 'compte':
            $admin = require_admin($user);
            $account = $id ? editable_account($db, $id, $admin['id']) : null;
            $values = $account ?? ['first_name' => '', 'last_name' => '', 'email' => ''];
            if ($common['error'] && input($_POST, 'action') === 'save-account') {
                foreach (['first_name', 'last_name', 'email'] as $field) {
                    $values[$field] = input($_POST, $field);
                }
            }
            return ['account-edit', $id ? 'Modifier le compte' : 'Ajouter un compte', [
                'id' => $id, 'account' => $account, 'values' => $values,
            ]];

        default:
            throw new InputException('Cette page est introuvable.', 404);
    }
}

function attempted_relation(array $common): ?array
{
    // L'arbre rouvre la modale avec la saisie précédente et le message de validation.
    if (!$common['error'] || !in_array(input($_POST, 'action'), ['save-relation', 'delete-relation'], true)) {
        return null;
    }
    return [
        'id' => id_value($_POST['relation_id'] ?? null),
        'a_id' => id_value($_POST['a_id'] ?? null), 'b_id' => id_value($_POST['b_id'] ?? null),
        'kind' => input($_POST, 'kind'), 'end_year' => input($_POST, 'end_year'), 'error' => $common['error'],
    ];
}

function attempted_relative(array $common): ?array
{
    if (!$common['error'] || input($_POST, 'action') !== 'create-relative') {
        return null;
    }
    return [
        'id' => id_value($_POST['reference_id'] ?? null), 'preset' => input($_POST, 'preset'),
        'firstName' => input($_POST, 'first_name'), 'lastName' => family_name(input($_POST, 'last_name')), 'error' => $common['error'],
        'sex' => array_key_exists('sex', $_POST) ? input($_POST, 'sex') : null,
    ];
}
