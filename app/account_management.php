<?php
declare(strict_types=1);

// Une demande ou une ancienne invitation donne accès à l'administration après validation.
const ACCOUNT_STATES = ['active' => 'Actif', 'pending' => 'En attente de validation', 'invited' => 'Invitation en cours'];

function first_admin_id(PDO $db): int
{
    return (int) $db->query('SELECT user_id FROM first_admin WHERE slot = 1')->fetchColumn();
}

function managed_accounts(PDO $db): array
{
    $accounts = $db->query(<<<'SQL'
        SELECT id, first_name, last_name, email, state, password_hash IS NOT NULL AS has_password
        FROM users ORDER BY last_name COLLATE NOCASE, first_name COLLATE NOCASE, id
        SQL)->fetchAll();
    return [
        'active' => array_filter($accounts, static fn (array $account): bool => $account['state'] === 'active'),
        'waiting' => array_filter($accounts, static fn (array $account): bool => $account['state'] !== 'active'),
    ];
}

function editable_account(PDO $db, int $id, int $adminId): array
{
    if ($id !== $adminId && $id === first_admin_id($db)) {
        throw new InputException('Ce compte est protégé. Seul son titulaire peut le modifier ou le supprimer.', 403);
    }
    $account = query($db, <<<'SQL'
        SELECT id, first_name, last_name, email, state, password_hash IS NOT NULL AS has_password
        FROM users WHERE id = ?
        SQL, [$id])->fetch();
    if (!$account) {
        throw new InputException('Ce compte est introuvable.', 404);
    }
    return $account;
}

function save_managed_account(PDO $db, int $id, array $form): void
{
    $identity = account_identity($form);
    $hash = confirmed_password($form, $id === 0);
    write_transaction($db, static function (PDO $db) use ($id, $identity, $hash): void {
        // Recontrôler la session sous le verrou d'écriture si un autre admin vient de supprimer le compte.
        $admin = require_admin(current_admin($db));
        $account = $id ? editable_account($db, $id, $admin['id']) : null;
        if ($account && !$account['has_password'] && $hash === null) {
            throw new InputException('Choisis un mot de passe pour ce compte.');
        }
        if (query($db, 'SELECT 1 FROM users WHERE email = ? AND id <> ?', [$identity['email'], $id])->fetchColumn()) {
            throw new InputException('Cette adresse est déjà utilisée par un compte.');
        }

        $parameters = $identity + ['password_hash' => $hash];
        if ($account === null) {
            query($db, "INSERT INTO users (first_name,last_name,email,password_hash,state)"
                . " VALUES (:first_name,:last_name,:email,:password_hash,'active')", $parameters);
            return;
        }

        // Modifier le mot de passe ou l'adresse d'une ancienne invitation retire son accès par jeton.
        $resetInvitation = $account['state'] === 'invited' && ($hash !== null || $identity['email'] !== $account['email']);
        query($db, <<<'SQL'
            UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email,
                password_hash = COALESCE(:password_hash, password_hash),
                state = CASE WHEN :reset = 1 THEN 'pending' ELSE state END,
                token_hash = CASE WHEN :reset = 1 THEN NULL ELSE token_hash END,
                token_expires = CASE WHEN :reset = 1 THEN NULL ELSE token_expires END
            WHERE id = :id
            SQL, $parameters + ['id' => $id, 'reset' => (int) $resetInvitation]);
    });
}

function decide_account_request(PDO $db, int $id, bool $approve): void
{
    write_transaction($db, static function (PDO $db) use ($id, $approve): void {
        $admin = require_admin(current_admin($db));
        $account = editable_account($db, $id, $admin['id']);
        if ($account['state'] === 'active') {
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
        $admin = require_admin(current_admin($db));
        editable_account($db, $id, $admin['id']);
        if ($id === $admin['id']) {
            // Le compte fondateur peut lever sa protection dans la transaction qui le supprime.
            query($db, 'DELETE FROM first_admin WHERE user_id = ?', [$id]);
        }
        query($db, 'DELETE FROM users WHERE id = ?', [$id]);
    });
}
