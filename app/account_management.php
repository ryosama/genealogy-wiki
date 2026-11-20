<?php
declare(strict_types=1);

// Les rôles sont modifiables pour les comptes actifs. Une demande publique est toujours une demande d'éditeur.
const ACCOUNT_ROLES = ['admin' => 'Administrateur', 'editor' => 'Éditeur'];
const ACCOUNT_STATES = ['active' => 'Actif', 'pending' => 'En attente de validation', 'invited' => 'Invitation à activer'];

function first_admin_id(PDO $db): int
{
    return (int) $db->query('SELECT user_id FROM first_admin WHERE slot = 1')->fetchColumn();
}

function managed_accounts(PDO $db): array
{
    $accounts = $db->query(<<<'SQL'
        SELECT id, first_name, last_name, email, role, state, password_hash IS NOT NULL AS has_password
        FROM users ORDER BY role, last_name COLLATE NOCASE, first_name COLLATE NOCASE, id
        SQL)->fetchAll();
    return [
        'active' => array_filter($accounts, static fn (array $account): bool => $account['state'] === 'active'),
        'waiting' => array_filter($accounts, static fn (array $account): bool => $account['state'] !== 'active'),
    ];
}

function editable_account(PDO $db, int $id): array
{
    if ($id === first_admin_id($db)) {
        throw new InputException('Le premier administrateur est protégé.', 403);
    }
    $account = query($db, <<<'SQL'
        SELECT id, first_name, last_name, email, role, state, password_hash IS NOT NULL AS has_password
        FROM users WHERE id = ?
        SQL, [$id])->fetch();
    if (!$account) {
        throw new InputException('Ce compte est introuvable.', 404);
    }
    return $account;
}

function save_managed_account(PDO $db, int $id, array $form): string
{
    $identity = account_identity($form);
    $hash = confirmed_password($form, $id === 0);
    return write_transaction($db, static function (PDO $db) use ($id, $form, $identity, $hash): string {
        // Recontrôler les droits sous le verrou d'écriture si un autre admin vient de les modifier.
        guard(viewer($db), true);
        $account = $id ? editable_account($db, $id) : null;
        if ($account && !$account['has_password'] && $hash === null) {
            throw new InputException('Choisis un mot de passe pour ce compte.');
        }
        $active = $account === null || $account['state'] === 'active';
        $role = $active ? input($form, 'role') : $account['role'];
        if (!isset(ACCOUNT_ROLES[$role])) {
            throw new InputException('Choisis un rôle valide.');
        }
        if (query($db, 'SELECT 1 FROM users WHERE email = ? AND id <> ?', [$identity['email'], $id])->fetchColumn()) {
            throw new InputException('Cette adresse est déjà utilisée par un compte.');
        }

        $parameters = $identity + ['role' => $role, 'password_hash' => $hash];
        if ($account === null) {
            query($db, "INSERT INTO users (first_name,last_name,email,password_hash,role,state)"
                . " VALUES (:first_name,:last_name,:email,:password_hash,:role,'active')", $parameters);
            return $role;
        }

        // Modifier le mot de passe ou l'adresse d'une ancienne invitation retire son accès par jeton.
        $resetInvitation = !$active && ($hash !== null || $identity['email'] !== $account['email']);
        query($db, <<<'SQL'
            UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, role = :role,
                password_hash = COALESCE(:password_hash, password_hash),
                state = CASE WHEN :reset = 1 THEN 'pending' ELSE state END,
                token_hash = CASE WHEN :reset = 1 THEN NULL ELSE token_hash END,
                token_expires = CASE WHEN :reset = 1 THEN NULL ELSE token_expires END
            WHERE id = :id
            SQL, $parameters + ['id' => $id, 'reset' => (int) $resetInvitation]);
        return $role;
    });
}

function decide_account_request(PDO $db, int $id, bool $approve): void
{
    write_transaction($db, static function (PDO $db) use ($id, $approve): void {
        guard(viewer($db), true);
        $account = editable_account($db, $id);
        if ($account['role'] !== 'editor' || $account['state'] === 'active') {
            throw new InputException('Ce compte ne fait pas partie des demandes en attente.');
        }
        if (!$approve) {
            query($db, 'DELETE FROM users WHERE id = ?', [$id]);
            return;
        }
        if (!$account['has_password']) {
            throw new InputException('Définis un mot de passe pour cette ancienne demande avant de la valider.');
        }
        query($db, "UPDATE users SET state = 'active', token_hash = NULL, token_expires = NULL WHERE id = ?", [$id]);
    });
}

function delete_managed_account(PDO $db, int $id): void
{
    write_transaction($db, static function (PDO $db) use ($id): void {
        guard(viewer($db), true);
        editable_account($db, $id);
        query($db, 'DELETE FROM users WHERE id = ?', [$id]);
    });
}
