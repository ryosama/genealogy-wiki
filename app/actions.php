<?php
declare(strict_types=1);

function process_action(PDO $db, string $action, int $personId, ?array $user): never
{
    check_csrf();

    // Les actions de compte sont accessibles avant la connexion. Les liens reçus par mail
    // sont vérifiés dans les fonctions métier, en plus du jeton de formulaire.
    switch ($action) {
        case 'login':
            throttle($db, 'login', 15);
            if (!sign_in($db, $_POST)) {
                throw new InputException('E-mail ou mot de passe incorrect.');
            }
            flash('Tu es connecté.');
            go();

        case 'logout':
            $_SESSION = [];
            session_regenerate_id(true);
            go();

        case 'request':
            throttle($db, 'request', 5);
            request_account($db, $_POST);
            flash('Si la demande peut être traitée, le mainteneur recevra un e-mail.');
            go('connexion');

        case 'review':
            $token = input($_POST, 'token');
            $decision = input($_POST, 'decision');
            if (!in_array($decision, ['approve', 'reject'], true)) {
                throw new InputException('Choisis une décision.');
            }
            review_account($db, $token, $decision === 'approve');
            flash($decision === 'approve' ? 'La demande a été validée.' : 'La demande a été refusée.');
            go();

        case 'activate':
            $token = input($_POST, 'token');
            account_token($db, $token, 'invited');
            if (($_POST['password'] ?? null) !== ($_POST['confirmation'] ?? null)) {
                throw new InputException('Les deux mots de passe doivent être identiques.');
            }
            activate_account($db, $token, secure_password($_POST['password'] ?? null));
            flash('Ton compte est actif. Tu peux te connecter.');
            go('connexion');
    }

    // Les modifications de contenu exigent un compte actif et conservent son nom dans l'historique.
    $editor = full_name(guard($user));
    switch ($action) {
        case 'save-person':
            $id = save_person($db, $personId, person_fields($_POST), $editor);
            flash($personId ? 'La fiche a été enregistrée.' : 'La fiche a été créée.');
            go('personne', ['id' => $id]);

        case 'save-relation':
        case 'delete-relation':
            // La fiche dans l'URL sert de point de retour. L'identifiant du lien vient du formulaire.
            find_person($db, $personId);
            $relationId = id_value($_POST['relation_id'] ?? null);
            if ($action === 'delete-relation') {
                delete_relation($db, $relationId, $editor);
                flash('La relation a été supprimée.');
            } else {
                $end = input($_POST, 'end_year');
                if ($end !== '' && (!preg_match('/^\d{1,4}$/D', $end) || (int) $end < 1)) {
                    throw new InputException('Indique une année de fin valide.');
                }
                $relation = relation_fields(id_value($_POST['a_id'] ?? null), id_value($_POST['b_id'] ?? null),
                    input($_POST, 'kind'), $end === '' ? null : (int) $end);
                save_relation($db, $relationId, $relation, $editor);
                flash('La relation a été enregistrée.');
            }
            go(input($_GET, 'view') === 'arbre' ? 'arbre' : 'personne', ['id' => $personId]);

        case 'upload':
            store_media($db, $personId, input($_POST, 'kind'), short_text($_POST, 'title', 180),
                $_FILES['file'] ?? [], $editor);
            flash('Le fichier a été ajouté.');
            go('personne', ['id' => $personId]);

        case 'delete-media':
            remove_media($db, $personId, id_value($_POST['media_id'] ?? null), $editor);
            flash('Le fichier a été supprimé.');
            go('personne', ['id' => $personId]);

        case 'delete-person':
            // La suppression d'une fiche et la fusion de doublons sont réservées au mainteneur.
            guard($user, true);
            remove_person($db, $personId, $editor);
            flash('La fiche a été supprimée.');
            go('personnes');

        case 'merge-person':
            guard($user, true);
            merge_people($db, $personId, id_value($_POST['duplicate_id'] ?? null), $editor);
            flash('Les fiches ont été fusionnées.');
            go('personne', ['id' => $personId]);
    }

    throw new InputException('Cette action est introuvable.', 404);
}
