<?php
declare(strict_types=1);

// Le diagnostic peut lire la configuration avant de charger le reste de l'application.
function load_config(string $root): array
{
    $local = $root . '/config/local.php';
    $config = array_replace([
        'data_dir' => $root . '/var',
        'app_url' => 'http://127.0.0.1:8000',
        'mail_mode' => 'outbox',
        'mail_from' => '',
        'image_tool' => 'convert',
    ], is_file($local) ? require $local : []);

    // Une instance de test peut utiliser sa propre base et ses propres liens.
    if (getenv('GENEALOGY_DATA_DIR')) {
        $config['data_dir'] = getenv('GENEALOGY_DATA_DIR');
    }
    if (getenv('GENEALOGY_APP_URL')) {
        $config['app_url'] = getenv('GENEALOGY_APP_URL');
    }
    return $config;
}
