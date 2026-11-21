<?php
declare(strict_types=1);

function process_action(PDO $db, string $action, int $personId, ?array $user): never
{
    check_csrf();

    // La demande et le choix d'un mot de passe via une ancienne invitation restent publics.
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
            verify_registration_challenge($_POST);
            request_account($db, $_POST);
            flash('Une nouvelle demande est placée en attente de validation par un administrateur.');
            go('connexion');

        case 'activate':
            $token = input($_POST, 'token');
            activation_account($db, $token);
            activate_account($db, $token, confirmed_password($_POST));
            flash('Ton mot de passe a été enregistré. Ta demande attend la validation d’un administrateur.');
            go('connexion');

        case 'save-account':
            require_admin($user);
            save_managed_account($db, $personId, $_POST);
            flash($personId ? 'Le compte a été modifié.' : 'Le compte a été ajouté.');
            go('comptes');

        case 'approve-account':
        case 'reject-account':
            $approve = $action === 'approve-account';
            decide_account_request($db, $personId, $approve);
            flash($approve ? 'La demande de compte a été validée.' : 'La demande de compte a été refusée.');
            go('comptes');

        case 'delete-account':
            delete_managed_account($db, $personId);
            if ($personId === $user['id']) {
                $_SESSION = [];
                session_regenerate_id(true);
                flash('Ton compte a été supprimé.');
                go();
            }
            flash('Le compte a été supprimé.');
            go('comptes');
    }

    // Les modifications de contenu exigent un administrateur et conservent son nom dans l'historique.
    $editor = full_name(require_admin($user));
    switch ($action) {
        case 'save-person':
            $id = save_person($db, $personId, person_fields($_POST), $editor);
            flash($personId ? 'La fiche a été enregistrée.' : 'La fiche a été créée.');
            go('personne', ['id' => $id]);

        case 'create-relative':
            $referenceId = id_value($_POST['reference_id'] ?? null);
            create_relative($db, $referenceId, input($_POST, 'preset'), $_POST, $editor);
            flash('La fiche et ses liens ont été créés.');
            go('arbre', ['id' => $referenceId]);

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
            $kind = input($_POST, 'kind');
            $mediaId = store_media($db, $personId, $kind, short_text($_POST, 'title', 180),
                $_FILES['file'] ?? [], $editor);
            if ($kind === 'photo') {
                flash('La photo a été ajoutée en WebP.');
                go('photo', ['id' => $personId, 'photo' => $mediaId]);
            }
            flash('Le fichier a été ajouté.');
            go('personne', ['id' => $personId]);

        case 'save-portrait':
            save_portrait($db, $personId, id_value($_GET['photo'] ?? null), $_POST, $editor);
            flash('Le médaillon a été enregistré.');
            go('personne', ['id' => $personId]);

        case 'save-photo':
            save_photo_edit($db, $personId, id_value($_GET['photo'] ?? null), $_POST, $editor);
            flash('La photo a été enregistrée.');
            go('personne', ['id' => $personId]);

        case 'delete-portrait':
            if (remove_portrait($db, $personId, id_value($_POST['media_id'] ?? null), $editor)) {
                flash('Le médaillon a été retiré.');
            }
            go('personne', ['id' => $personId]);

        case 'delete-media':
            remove_media($db, $personId, id_value($_POST['media_id'] ?? null), $editor);
            flash('Le fichier a été supprimé.');
            go('personne', ['id' => $personId]);

        case 'delete-person':
            $targetId = array_key_exists('target_id', $_POST) ? id_value($_POST['target_id']) : $personId;
            remove_person($db, $targetId, $editor);
            flash('La fiche a été supprimée.');
            if (input($_GET, 'view') === 'arbre') {
                go('arbre', $targetId === $personId ? [] : ['id' => $personId]);
            }
            if ($targetId !== $personId) {
                go('personne', ['id' => $personId]);
            }
            go('personnes');

        case 'merge-person':
            merge_people($db, $personId, id_value($_POST['duplicate_id'] ?? null), $editor);
            flash('Les fiches ont été fusionnées.');
            go('personne', ['id' => $personId]);
    }

    throw new InputException('Cette action est introuvable.', 404);
}
