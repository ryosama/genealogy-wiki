<?php
declare(strict_types=1);

// Le diagnostic peut s'exécuter avec PHP 7.4 pour signaler un interpréteur trop ancien.
const RACINES_MINIMUM_PHP = '8.3';
const RACINES_PHP_MODULES = [
    'pdo' => 'common',
    'pdo_sqlite' => 'sqlite3',
    'dom' => 'xml',
    'fileinfo' => 'common',
    'filter' => 'common',
    'mbstring' => 'mbstring',
    'session' => 'common',
];

function check_executable(string $name, bool $includeSbin = false): ?string
{
    if ($name === '') {
        return null;
    }
    if (strpos($name, '/') !== false) {
        return is_file($name) && is_executable($name) ? $name : null;
    }
    $directories = explode(PATH_SEPARATOR, (string) getenv('PATH'));
    if ($includeSbin) {
        // Un utilisateur non-root n'a pas toujours les commandes serveur dans son PATH.
        $directories = array_merge($directories, ['/usr/sbin', '/usr/local/sbin', '/sbin']);
    }
    foreach ($directories as $directory) {
        $path = ($directory === '' ? '.' : $directory) . '/' . $name;
        if (is_file($path) && is_executable($path)) {
            return $path;
        }
    }
    return null;
}

function check_command(array $command): ?string
{
    if (!function_exists('proc_open') || PHP_VERSION_ID < 70400) {
        return null;
    }
    // Ne jamais interpréter les arguments comme une commande shell. Ne pas afficher la sortie brute de php-fpm.
    $process = @proc_open($command, [['file', '/dev/null', 'r'], ['pipe', 'w'], ['file', '/dev/null', 'w']], $pipes);
    if (!is_resource($process)) {
        return null;
    }
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    return proc_close($process) === 0 && $output !== false ? $output : null;
}

function check_php(string $label, string $sapi, string $version, array $modules, string $aptDefault): array
{
    $supported = version_compare($version, RACINES_MINIMUM_PHP, '>=');
    $series = $supported ? implode('.', array_slice(explode('.', $version), 0, 2)) : $aptDefault;
    $prefix = 'php' . $series . '-';
    $checks = [[
        $supported, "$label $version (minimum " . RACINES_MINIMUM_PHP . ')', [$prefix . $sapi],
        $supported ? '' : 'Utiliser PHP ' . RACINES_MINIMUM_PHP . ' ou une version plus récente.',
    ]];

    foreach (RACINES_PHP_MODULES as $name => $packageSuffix) {
        $loaded = in_array($name, $modules, true);
        $checks[] = [$loaded, "$label · extension $name", [$prefix . $packageSuffix],
            "Si le paquet est déjà installé, activer $name pour PHP $series ($sapi)."];
    }
    if (!$supported) {
        // Les modules présents dans l'ancienne installation doivent exister aussi dans le nouveau PHP.
        foreach (array_unique(RACINES_PHP_MODULES) as $packageSuffix) {
            $checks[0][2][] = $prefix . $packageSuffix;
        }
    }
    return $checks;
}

function check_image_tool(string $configured): array
{
    $binary = check_executable($configured);
    if ($binary === null) {
        foreach (['magick', 'convert'] as $alternative) {
            if (check_executable($alternative) !== null) {
                return [[false, "ImageMagick · commande configurée indisponible ($configured)", [],
                    "Essayer '$alternative' dans image_tool (config/local.php), puis relancer le diagnostic."]];
            }
        }
        return [[false, "ImageMagick · commande configurée indisponible ($configured)", ['imagemagick'],
            'Après installation, régler image_tool sur la commande fournie (convert ou magick).']];
    }
    $output = check_command([$binary, '-list', 'format']);
    if ($output === null) {
        return [[false, "ImageMagick · impossible de lister les formats avec $configured", ['imagemagick'],
            "Essayer $configured -list format et vérifier la configuration de l'outil."]];
    }

    // Avec ImageMagick 6 ou 7, le nom du module est facultatif et le mode indique la lecture et l'écriture.
    preg_match_all('/^[ \t]*([A-Z0-9]+)\*?[ \t]+(?:\S+[ \t]+)?([r-][w-][+-])(?=[ \t\r]|$)/mi', $output, $formats, PREG_SET_ORDER);
    $modes = [];
    foreach ($formats as $format) {
        $modes[strtoupper($format[1])] = strtolower($format[2]);
    }
    $checks = [[true, "ImageMagick · $configured"]];
    // Certains paquets Debian et Ubuntu distribuent ces décodeurs comme modules de libheif.
    $decoders = ['AVIF' => ['libheif-plugin-aomdec'], 'HEIC' => ['libheif-plugin-libde265']];
    foreach (['JPEG', 'PNG', 'WEBP', 'AVIF', 'HEIC', 'BMP'] as $format) {
        $mode = $modes[$format] ?? '---';
        $read = $mode[0] === 'r';
        $checks[] = [$read, "ImageMagick · lecture $format", array_merge(['imagemagick'], $decoders[$format] ?? []),
            "Utiliser une version d'ImageMagick qui fournit le décodeur $format."];
    }
    $checks[] = [($modes['WEBP'][1] ?? '-') === 'w', 'ImageMagick · écriture WebP', ['imagemagick'],
        "Utiliser une version d'ImageMagick qui fournit l'encodeur WebP."];
    return $checks;
}

function check_server(string $aptDefault): array
{
    $checks = [[check_executable('nginx', true) !== null, 'Nginx', ['nginx']]];
    $cliSeries = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    $binary = check_executable('php-fpm' . $cliSeries, true) ?? check_executable('php-fpm', true);
    if ($binary === null) {
        // Les binaires Debian et Ubuntu sont versionnés. PHP-FPM peut être plus récent que le CLI.
        $candidates = array_merge(glob('/usr/sbin/php-fpm[0-9]*') ?: [], glob('/usr/local/sbin/php-fpm[0-9]*') ?: []);
        rsort($candidates, SORT_NATURAL);
        foreach ($candidates as $candidate) {
            $binary = check_executable($candidate);
            if ($binary !== null) {
                break;
            }
        }
    }
    if ($binary === null) {
        $checks[] = [false, 'PHP-FPM', ['php' . $aptDefault . '-fpm']];
        return $checks;
    }

    $version = check_command([$binary, '-v']);
    $modules = check_command([$binary, '-m']);
    if ($modules === null || !preg_match('/^PHP ([0-9]+(?:\.[0-9]+)+)/m', $version ?? '', $match)) {
        $checks[] = [false, 'PHP-FPM · version ou extensions illisibles', [],
            "Exécuter $binary -v et $binary -m pour diagnostiquer l'installation."];
        return $checks;
    }
    $modules = array_map('trim', preg_split('/\R/', strtolower($modules)));
    return array_merge($checks, check_php('PHP-FPM', 'fpm', $match[1], $modules, $aptDefault));
}

function check_mail(array $config): array
{
    if ($config['mail_mode'] === 'outbox') {
        return [[true, 'Courriels · boîte d’envoi locale (outbox)']];
    }
    if ($config['mail_mode'] !== 'mail') {
        return [[false, 'Courriels · mode inconnu', [], 'Choisir outbox ou mail dans config/local.php.']];
    }

    preg_match('/^\s*(?:"([^"]+)"|\'([^\']+)\'|(\S+))/', (string) ini_get('sendmail_path'), $path);
    $executable = ($path[1] ?? '') ?: (($path[2] ?? '') ?: ($path[3] ?? ''));
    return [
        [function_exists('mail'), 'PHP CLI · fonction mail()', [], 'Activer mail() dans le php.ini utilisé.'],
        [check_executable($executable, true) !== null, 'Courriels · agent d’envoi configuré pour le CLI', ['msmtp-mta'],
            'Configurer le relais de messagerie et sendmail_path, ou choisir mail_mode=outbox.'],
        [function_exists('filter_var') && filter_var($config['mail_from'], FILTER_VALIDATE_EMAIL) !== false, 'Courriels · adresse d’expédition', [],
            'Renseigner une adresse valide dans mail_from (config/local.php).'],
    ];
}

function check_installation(array $packages, ?string $aptCache): void
{
    if (!$packages) {
        return;
    }
    $packages = array_values(array_unique($packages));
    echo "\nInstallation proposée\n";
    if (check_executable('apt-get', true) === null) {
        echo 'Installer les composants signalés avec le gestionnaire de la distribution. '
            . "Noms des paquets Debian et Ubuntu pour référence.\n  " . implode(' ', $packages) . "\n";
        return;
    }

    // Un paquet d'une autre version de PHP ne peut pas fournir un module à l'interpréteur actuel.
    $metadata = $aptCache ? check_command(array_merge([$aptCache, 'show'], $packages)) : null;
    if ($metadata !== null) {
        preg_match_all('/^Package: (\S+)$/m', $metadata, $matches);
        $unavailable = array_diff($packages, $matches[1]);
        $packages = array_intersect($packages, $matches[1]);
        if ($unavailable) {
            echo 'Paquets hors de l’index APT local' . "\n  " . implode(' ', $unavailable) . "\n";
            echo "Actualiser l’index avec sudo apt update ou utiliser les paquets adaptés à la version de la distribution.\n";
        }
    } else {
        echo "Exemple pour Debian et Ubuntu. Vérifier la disponibilité des paquets dans APT.\n";
    }
    if ($packages) {
        echo '  sudo apt install ' . implode(' ', $packages) . "\n";
    }
}

function check_requirements(array $config, bool $production): int
{
    $aptCache = check_executable('apt-cache');
    $aptDefault = RACINES_MINIMUM_PHP;
    $default = $aptCache ? check_command([$aptCache, 'depends', 'php-cli']) : null;
    if (preg_match('/php([0-9]+\.[0-9]+)-cli\b/', $default ?? '', $match)
        && version_compare($match[1], RACINES_MINIMUM_PHP, '>=')) {
        $aptDefault = $match[1];
    }

    $checks = check_php('PHP CLI', 'cli', PHP_VERSION, array_map('strtolower', get_loaded_extensions()), $aptDefault);
    $canProbe = function_exists('proc_open') && PHP_VERSION_ID >= 70400;
    $checks[] = [$canProbe, 'PHP CLI · lancement des convertisseurs avec proc_open()', [],
        'Utiliser une version de PHP compatible et vérifier disable_functions dans le php.ini.'];
    if ($canProbe) {
        $checks = array_merge($checks, check_image_tool($config['image_tool']));
    } else {
        $checks[] = [null, 'ImageMagick · vérification des formats à relancer après activation de proc_open()'];
    }
    $checks = array_merge($checks, check_mail($config));
    if ($production) {
        $checks = array_merge($checks, check_server($aptDefault));
    }

    $missing = 0;
    $packages = [];
    foreach ($checks as $row) {
        [$ok, $label] = $row;
        echo ($ok === null ? '[INFO] ' : ($ok ? '[OK] ' : '[MANQUE] ')) . $label . "\n";
        if ($ok === false) {
            $missing++;
            $packages = array_merge($packages, $row[2] ?? []);
            if (!empty($row[3])) {
                echo '  ' . $row[3] . "\n";
            }
        }
    }
    check_installation($packages, $aptCache);
    if ($production) {
        echo "\nLe contrôle PHP-FPM porte sur le binaire installé et ses modules. "
            . "Vérifier aussi le pool utilisé par Nginx et ses réglages de téléversement et de courriels.\n";
    }
    echo "\nLes fichiers PHP et JavaScript du dépôt sont prêts à l’emploi.\n";
    echo $missing ? "$missing point(s) à corriger.\n" : "Tous les prérequis vérifiés sont présents.\n";
    return $missing ? 1 : 0;
}
