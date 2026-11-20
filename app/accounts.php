<?php
declare(strict_types=1);

// Fenêtre de comptage des tentatives pour une adresse IP et une action, en secondes.
const AUTH_WINDOW = 15 * 60;

function current_admin(PDO $db): ?array
{
    // La clé admin_id exige une nouvelle connexion pour les sessions ouvertes avant le changement de droits.
    $id = $_SESSION['admin_id'] ?? null;
    return $id ? (query($db, "SELECT * FROM users WHERE id = ? AND state = 'active'", [$id])->fetch() ?: null) : null;
}

function require_admin(?array $user): array
{
    if (!$user) {
        throw new InputException('Connecte-toi avec un compte administrateur pour effectuer cette action.', 403);
    }
    return $user;
}

function throttle(PDO $db, string $purpose, int $maximum): void
{
    // Une clé distincte par action permet de limiter séparément les demandes et les connexions.
    $bucket = hash('sha256', $purpose . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'local'));
    $now = time();
    $attempts = write_transaction($db, static function (PDO $db) use ($bucket, $now): int {
        // Réinitialiser les fenêtres expirées puis compter la tentative sans conflit concurrent.
        query($db, 'DELETE FROM auth_limits WHERE expires_at <= ?', [$now]);
        query($db, 'INSERT INTO auth_limits (bucket,expires_at,attempts) VALUES (?,?,1)'
            . ' ON CONFLICT(bucket) DO UPDATE SET attempts = attempts + 1', [$bucket, $now + AUTH_WINDOW]);
        return (int) query($db, 'SELECT attempts FROM auth_limits WHERE bucket = ?', [$bucket])->fetchColumn();
    });
    if ($attempts > $maximum) {
        throw new InputException('Trop de tentatives. Réessaie dans quelques minutes.', 429);
    }
}

function valid_email(string $email): string
{
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InputException('Saisis une adresse e-mail valide.');
    }
    return strtolower($email);
}

function secure_password(mixed $password): string
{
    // Les 72 octets correspondent à la limite prise en charge par bcrypt. PHP génère le sel.
    if (!is_string($password) || str_contains($password, "\0") || strlen($password) > 72 || mb_strlen($password) < 12) {
        throw new InputException('Le mot de passe doit contenir au moins 12 caractères et au plus 72 octets.');
    }
    return password_hash($password, PASSWORD_DEFAULT);
}

function sign_in(PDO $db, array $form): bool
{
    $email = input($form, 'email');
    $password = $form['password'] ?? null;
    $user = query($db, "SELECT id, password_hash FROM users WHERE email = ? AND state = 'active'", [$email])->fetch();
    // Un hachage fictif permet d'effectuer aussi une vérification quand l'adresse est inconnue.
    $hash = $user['password_hash'] ?? '$2y$10$W6rXrSM4qG2G3B2rcfe3HugOS4.gmt/ZtYrqGrN6XVEx2SRSmE7py';
    if (!is_string($password) || strlen($password) > 72 || !password_verify($password, $hash) || empty($user['password_hash'])) {
        return false;
    }
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        query($db, 'UPDATE users SET password_hash = ? WHERE id = ? AND password_hash = ?',
            [password_hash($password, PASSWORD_DEFAULT), $user['id'], $hash]);
    }
    // Renouveler l'identifiant de la session au moment où elle acquiert des droits.
    session_regenerate_id(true);
    $_SESSION = ['admin_id' => $user['id']];
    return true;
}

function account_identity(array $form): array
{
    return [
        'first_name' => required_text($form, 'first_name', 'Le prénom', 100),
        'last_name' => required_text($form, 'last_name', 'Le nom', 100),
        'email' => valid_email(input($form, 'email')),
    ];
}

function confirmed_password(array $form, bool $required = true): ?string
{
    $password = $form['password'] ?? '';
    if ($password !== ($form['confirmation'] ?? '')) {
        throw new InputException('Les deux mots de passe doivent être identiques.');
    }
    return !$required && $password === '' ? null : secure_password($password);
}

function request_account(PDO $db, array $form): void
{
    $identity = account_identity($form);
    $hash = confirmed_password($form);
    // Conserver une demande existante ou un compte actif. Le message public est le même dans les deux cas.
    query($db, "INSERT INTO users (first_name,last_name,email,password_hash,state)"
        . " VALUES (:first_name,:last_name,:email,:password_hash,'pending') ON CONFLICT(email) DO NOTHING",
        $identity + ['password_hash' => $hash]);
}

function activation_account(PDO $db, string $token): array
{
    // Une ancienne invitation reste utilisable pour choisir un mot de passe avant validation.
    $user = preg_match('/^[a-f0-9]{64}$/D', $token)
        ? query($db, "SELECT * FROM users WHERE token_hash = ? AND token_expires >= ? AND state = 'invited'",
            [hash('sha256', $token), time()])->fetch()
        : false;
    if (!$user) {
        throw new InputException('Ce lien a expiré ou a déjà été utilisé.', 404);
    }
    return $user;
}

function activate_account(PDO $db, string $token, string $hash): void
{
    write_transaction($db, static function (PDO $db) use ($token, $hash): void {
        // Vérifier et consommer l'invitation dans la même transaction.
        $user = activation_account($db, $token);
        query($db, "UPDATE users SET state = 'pending', password_hash = ?, token_hash = NULL, token_expires = NULL WHERE id = ?",
            [$hash, $user['id']]);
    });
}
