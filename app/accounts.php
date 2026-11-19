<?php
declare(strict_types=1);

// Durée de validité des liens de décision et d'activation, en secondes.
const TOKEN_LIFETIME = 3 * 24 * 60 * 60;
// Fenêtre de comptage des tentatives pour une adresse IP et une action, en secondes.
const AUTH_WINDOW = 15 * 60;

function viewer(PDO $db): ?array
{
    // La session mémorise seulement l'identifiant. Les droits actuels viennent de la base.
    $id = $_SESSION['user_id'] ?? null;
    return $id ? (query($db, "SELECT * FROM users WHERE id = ? AND state = 'active'", [$id])->fetch() ?: null) : null;
}

function guard(?array $user, bool $admin = false): array
{
    if (!$user || ($admin && $user['role'] !== 'admin')) {
        throw new InputException('Connecte-toi avec un compte autorisé pour effectuer cette action.', 403);
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
    if (!is_string($password) || strlen($password) > 72 || !password_verify($password, $hash) || !$user) {
        return false;
    }
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        query($db, 'UPDATE users SET password_hash = ? WHERE id = ? AND password_hash = ?',
            [password_hash($password, PASSWORD_DEFAULT), $user['id'], $hash]);
    }
    // Renouveler l'identifiant de la session au moment où elle acquiert des droits.
    session_regenerate_id(true);
    $_SESSION = ['user_id' => $user['id']];
    return true;
}

function send_message(string $to, string $subject, string $body): void
{
    global $config;
    $body .= "\n";
    if ($config['mail_mode'] === 'outbox') {
        // En développement, garder les messages et leurs liens dans un dossier privé.
        $outbox = private_directory($config['data_dir'] . '/outbox');
        $target = $outbox . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.eml';
        if (file_put_contents($target, "To: $to\nSubject: $subject\n\n$body", LOCK_EX) === false) {
            throw new RuntimeException('Impossible d’écrire dans la boîte d’envoi locale.');
        }
        chmod($target, 0600);
        return;
    }
    $from = valid_email($config['mail_from']);
    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');
    if ($config['mail_mode'] !== 'mail' || !mail($to, $encodedSubject, $body, [
        'From' => $from,
        'Content-Type' => 'text/plain; charset=UTF-8',
        'MIME-Version' => '1.0',
    ])) {
        throw new RuntimeException('L’envoi du message a échoué.');
    }
}

function request_account(PDO $db, array $form): void
{
    $name = [
        'first_name' => required_text($form, 'first_name', 'Le prénom', 100),
        'last_name' => required_text($form, 'last_name', 'Le nom', 100),
    ];
    $email = valid_email(input($form, 'email'));
    write_transaction($db, static function (PDO $db) use ($name, $email): void {
        // Le premier mainteneur actif reçoit les demandes de compte.
        $maintainer = $db->query("SELECT email FROM users WHERE role = 'admin' AND state = 'active' ORDER BY id LIMIT 1")->fetchColumn();
        if (!$maintainer) {
            throw new InputException('Le mainteneur doit encore configurer les demandes de compte.', 503);
        }
        $existing = query($db, 'SELECT * FROM users WHERE email = ?', [$email])->fetch();
        if ($existing && ($existing['state'] === 'active' || $existing['token_expires'] >= time())) {
            return;
        }
        // Le lien reçoit le jeton brut. La base conserve son empreinte et sa date de péremption.
        $token = bin2hex(random_bytes(32));
        $params = [$name['first_name'], $name['last_name'], $email, hash('sha256', $token), time() + TOKEN_LIFETIME];
        query($db, "INSERT INTO users (first_name,last_name,email,token_hash,token_expires,role,state)"
            . " VALUES (?,?,?,?,?,'editor','pending') ON CONFLICT(email) DO UPDATE SET first_name=excluded.first_name,"
            . " last_name=excluded.last_name,token_hash=excluded.token_hash,token_expires=excluded.token_expires,state='pending'", $params);
        $link = absolute_url('demande', ['token' => $token]);
        send_message($maintainer, 'Demande de compte éditeur', full_name($name) . " ($email) souhaite contribuer.\n\n"
            . "Ouvre ce lien pour valider ou refuser la demande.\n$link\n\nCe lien expire dans trois jours.");
    });
}

function account_token(PDO $db, string $token, string $state): array
{
    // L'état attendu distingue un lien de décision d'un lien d'activation.
    $user = preg_match('/^[a-f0-9]{64}$/D', $token)
        ? query($db, 'SELECT * FROM users WHERE token_hash = ? AND token_expires >= ? AND state = ?',
            [hash('sha256', $token), time(), $state])->fetch()
        : false;
    if (!$user) {
        throw new InputException('Ce lien a expiré ou a déjà été utilisé.', 404);
    }
    return $user;
}

function review_account(PDO $db, string $token, bool $approve): void
{
    write_transaction($db, static function (PDO $db) use ($token, $approve): void {
        $user = account_token($db, $token, 'pending');
        if (!$approve) {
            send_message($user['email'], 'Demande de compte', 'Ta demande de compte éditeur a été refusée.');
            query($db, 'DELETE FROM users WHERE id = ?', [$user['id']]);
            return;
        }

        // Remplacer l'empreinte du lien de décision. L'invitation suivante a sa propre validité.
        $invite = bin2hex(random_bytes(32));
        query($db, "UPDATE users SET state = 'invited', token_hash = ?, token_expires = ? WHERE id = ?",
            [hash('sha256', $invite), time() + TOKEN_LIFETIME, $user['id']]);
        $link = absolute_url('activation', ['token' => $invite]);
        send_message($user['email'], 'Active ton compte éditeur', "Ta demande de compte a été validée.\n\n"
            . "Choisis ton mot de passe via ce lien.\n$link\n\nCe lien expire dans trois jours.");
    });
}

function activate_account(PDO $db, string $token, string $hash): void
{
    write_transaction($db, static function (PDO $db) use ($token, $hash): void {
        // Vérifier et consommer l'invitation dans la même transaction.
        $user = account_token($db, $token, 'invited');
        query($db, "UPDATE users SET state = 'active', password_hash = ?, token_hash = NULL, token_expires = NULL WHERE id = ?",
            [$hash, $user['id']]);
    });
}
