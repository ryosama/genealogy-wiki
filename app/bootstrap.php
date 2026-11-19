<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
define('MEDIA_LIMIT', 12 * 1024 * 1024);

$config = array_replace([
    'data_dir' => PROJECT_ROOT . '/var',
    'app_url' => 'http://127.0.0.1:8000',
    'mail_mode' => 'outbox',
    'mail_from' => '',
    'image_tool' => 'convert',
], is_file(PROJECT_ROOT . '/config/local.php') ? require PROJECT_ROOT . '/config/local.php' : []);

// Isolate a demo or a test instance from the site's live SQLite database.
if (getenv('GENEALOGY_DATA_DIR')) {
    $config['data_dir'] = getenv('GENEALOGY_DATA_DIR');
}
if (getenv('GENEALOGY_APP_URL')) {
    $config['app_url'] = getenv('GENEALOGY_APP_URL');
}

date_default_timezone_set('Europe/Paris');

final class InputException extends RuntimeException {}

function e(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $view = '', array $params = []): string
{
    return './' . ($view === '' && !$params ? '' : '?' . http_build_query(
        ($view !== '' ? ['view' => $view] : []) + $params,
        '',
        '&',
        PHP_QUERY_RFC3986
    ));
}

function asset(string $name): string
{
    return './assets/' . $name;
}

function absolute_url(string $view, array $params): string
{
    global $config;
    return rtrim($config['app_url'], '/') . '/' . substr(url($view, $params), 2);
}

function go(string $view = '', array $params = []): never
{
    header('Location: ' . url($view, $params), true, 303);
    exit;
}

function input(array $source, string $key): string
{
    return isset($source[$key]) && is_string($source[$key]) ? trim($source[$key]) : '';
}

function id_value(mixed $value): int
{
    return is_scalar($value) ? (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0) : 0;
}

function full_name(array $entity): string
{
    return $entity['first_name'] . ' ' . $entity['last_name'];
}

function required_text(array $source, string $key, string $label, int $length): string
{
    $value = input($source, $key);
    if ($value === '' || mb_strlen($value) > $length) {
        throw new InputException("$label doit contenir entre 1 et $length caractères.");
    }
    return $value;
}

function short_text(array $source, string $key, int $length): string
{
    $value = input($source, $key);
    if (mb_strlen($value) > $length) {
        throw new InputException("Un des champs dépasse $length caractères.");
    }
    return $value;
}

function display_instant(string $value, bool $includeTime = false): string
{
    $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
    return $date->setTimezone(new DateTimeZone(date_default_timezone_get()))->format($includeTime ? 'd/m/Y à H:i' : 'd/m/Y');
}

function json_data(mixed $value): string
{
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE |
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

function private_directory(string $directory): string
{
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Impossible de créer le répertoire de données.');
    }
    $public = realpath(PROJECT_ROOT . '/public');
    $realDirectory = realpath($directory);
    if ($realDirectory === false || $realDirectory === $public || str_starts_with($realDirectory . '/', $public . '/')) {
        throw new RuntimeException('Le répertoire de données doit être situé hors du répertoire public.');
    }
    return $realDirectory;
}

function db(): PDO
{
    global $config;
    static $db;
    if ($db instanceof PDO) {
        return $db;
    }

    $directory = private_directory($config['data_dir']);
    $db = new PDO('sqlite:' . $directory . '/genealogy.sqlite', options: [
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec('PRAGMA foreign_keys = ON');
    if ((int) $db->query('PRAGMA user_version')->fetchColumn() === 0) {
        write_transaction($db, static function (PDO $db): void {
            if ((int) $db->query('PRAGMA user_version')->fetchColumn() === 0) {
                $db->exec(file_get_contents(PROJECT_ROOT . '/app/schema.sql'));
            }
        });
    }
    return $db;
}

// Take the SQLite writer lock before reading anything used to validate an edit.
function write_transaction(PDO $db, callable $write): mixed
{
    $db->exec('BEGIN IMMEDIATE');
    try {
        $result = $write($db);
        $db->exec('COMMIT');
        return $result;
    } catch (Throwable $error) {
        $db->exec('ROLLBACK');
        throw $error;
    }
}

function query(PDO $db, string $sql, array $params = []): PDOStatement
{
    $statement = $db->prepare($sql);
    foreach ($params as $key => $value) {
        // Recursive SQLite queries can compare values without affinity from a table column.
        $type = match (true) {
            is_int($value) => PDO::PARAM_INT,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
        $statement->bindValue(is_int($key) ? $key + 1 : ':' . $key, $value, $type);
    }
    $statement->execute();
    return $statement;
}

function view(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require PROJECT_ROOT . '/views/' . $name . '.php';
}

function flash(string $message): void
{
    $_SESSION['flash'] = $message;
}

function take_flash(): ?string
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">';
}

function check_csrf(): void
{
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], input($_POST, 'csrf'))) {
        throw new InputException('Ce formulaire a expiré. Recharge la page avant de réessayer.');
    }
}
