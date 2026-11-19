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
            $filters = ['name' => input($_GET, 'name'), 'year' => input($_GET, 'year'), 'when' => input($_GET, 'when')];
            try {
                $results = search_people($db, $filters, max(1, id_value($_GET['page'] ?? null)));
            } catch (InputException $error) {
                $results = ['people' => [], 'total' => 0, 'page' => 1, 'pages' => 0, 'error' => $error->getMessage()];
            }
            return ['directory', 'Personnes', $results + ['filters' => $filters]];

        case 'personne':
            $person = find_person($db, $id);
            $person['portrait_id'] = (int) query($db, 'SELECT media_id FROM portraits WHERE person_id = ?', [$id])->fetchColumn();
            // Les listes utilisent les métadonnées. Le navigateur charge chaque fichier à son URL.
            $media = query($db, 'SELECT id,kind,mime,title FROM media WHERE person_id = ? ORDER BY id', [$id])->fetchAll();
            $photos = array_values(array_filter($media, static fn ($item) => $item['kind'] === 'photo'));
            $documents = array_values(array_filter($media, static fn ($item) => $item['kind'] === 'document'));
            $duplicates = $user && $user['role'] === 'admin'
                ? query($db, 'SELECT id,first_name,last_name,birth_year FROM people WHERE id <> ? ORDER BY search_name', [$id])->fetchAll()
                : [];
            return ['person', full_name($person), [
                'person' => $person, 'photos' => $photos, 'documents' => $documents, 'duplicates' => $duplicates,
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

        case 'edition':
            guard($user);
            $person = $id ? find_person($db, $id) : [];
            if ($common['error'] && input($_POST, 'action') === 'save-person') {
                // Récupérer la saisie après un refus, en nettoyant le HTML avant de le réafficher.
                foreach (['first_name', 'last_name', 'sex', 'birth_date', 'death_date', 'birth_place', 'death_place', 'residence',
                    'biography', 'notable_facts'] as $field) {
                    $value = input($_POST, $field);
                    if ($field === 'biography' || $field === 'notable_facts') {
                        $value = bio_html(mb_strcut($value, 0, 70000));
                    }
                    $person[$field] = $value;
                }
            }
            return ['edit', $id ? 'Modifier la fiche' : 'Nouvelle personne', ['person' => $person, 'id' => $id]];

        case 'medaillon':
            guard($user);
            $person = find_person($db, $id);
            $editor = portrait_editor_data($db, $id, id_value($_GET['photo'] ?? null));
            return ['portrait', $editor['saved'] ? 'Ajuster le médaillon' : 'Créer le médaillon', $editor + ['person' => $person]];

        case 'historique':
            guard($user);
            $person = $id ? find_person($db, $id) : null;
            if (!$person) {
                guard($user, true);
            }
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
            return ['account', 'Demander un compte', ['mode' => 'request']];

        case 'demande':
        case 'activation':
            // Ouvrir un lien dans un mail affiche le formulaire. La décision passe ensuite par POST.
            $pending = $route === 'demande';
            $token = input($_GET, 'token');
            $account = account_token($db, $token, $pending ? 'pending' : 'invited');
            return ['account', $pending ? 'Demande de compte' : 'Activer le compte', [
                'mode' => $pending ? 'review' : 'activate', 'token' => $token, 'account' => $account,
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
        'firstName' => input($_POST, 'first_name'), 'lastName' => input($_POST, 'last_name'), 'error' => $common['error'],
        'sex' => array_key_exists('sex', $_POST) ? input($_POST, 'sex') : null,
    ];
}
