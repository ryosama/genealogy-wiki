<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Le point d'entrée HTTP charge le socle, les règles métier et les contrôleurs de pages.
require dirname(__DIR__) . '/app/bootstrap.php';
require PROJECT_ROOT . '/app/content.php';
require PROJECT_ROOT . '/app/people.php';
require PROJECT_ROOT . '/app/relatives.php';
require PROJECT_ROOT . '/app/accounts.php';
require PROJECT_ROOT . '/app/media.php';
require PROJECT_ROOT . '/app/actions.php';
require PROJECT_ROOT . '/app/pages.php';

header("Content-Security-Policy: default-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

// L'URL configurée couvre aussi le cas où un proxy termine la connexion HTTPS.
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || str_starts_with($config['app_url'], 'https://');
session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax']);
session_name('genealogy_session');
ini_set('session.use_strict_mode', '1');

$route = input($_GET, 'view');
$id = id_value($_GET['id'] ?? null);
// Données de mise en page partagées, initialisées pour pouvoir afficher aussi les erreurs.
$common = ['route' => $route, 'user' => null, 'notice' => null, 'error' => null];

try {
    $db = db();
    // Les fichiers publics ont leur propre réponse HTTP, sans gabarit ni session de visiteur.
    if ($route === 'media' && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
        serve_media($db, $id);
    }
    session_save_path(private_directory($config['data_dir'] . '/sessions'));
    if (!session_start()) {
        throw new RuntimeException('La session PHP est indisponible.');
    }
    $common['user'] = viewer($db);
    $common['notice'] = take_flash();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
                throw new InputException('Le formulaire dépasse la taille autorisée.', 413);
            }
            process_action($db, input($_POST, 'action'), $id, $common['user']);
        } catch (InputException $error) {
            $httpStatus = $error->getCode() ?: 422;
            if ($httpStatus === 403 || $httpStatus === 404) {
                throw $error;
            }
            // Une erreur de saisie réaffiche le formulaire et laisse l'utilisateur le corriger.
            http_response_code($httpStatus);
            $common['error'] = $error->getMessage();
        }
    }

    [$template, $title, $data] = page_data($db, $route, $id, $common['user'], $common);
} catch (InputException $error) {
    http_response_code($error->getCode() ?: 422);
    [$template, $title, $data] = ['error', 'Page indisponible', ['message' => $error->getMessage()]];
    $common['error'] = null;
} catch (Throwable $error) {
    error_log((string) $error);
    http_response_code(500);
    [$template, $title, $data] = ['error', 'Service indisponible', ['message' => 'La page est momentanément indisponible.']];
}

view('layout', $data + $common + ['template' => $template, 'title' => $title]);
