<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

try {
    $command = $argv[1] ?? 'help';
    if ($command === 'check') {
        $options = array_slice($argv, 2);
        if ($options !== [] && $options !== ['--production']) {
            fwrite(STDERR, "Usage php bin/console.php check [--production]\n");
            exit(1);
        }
        // Charger uniquement la configuration pour diagnostiquer même un PHP incomplet.
        define('PROJECT_ROOT', dirname(__DIR__));
        require PROJECT_ROOT . '/app/config.php';
        require __DIR__ . '/check.php';
        exit(check_requirements(load_config(PROJECT_ROOT), $options !== []));
    }

    require dirname(__DIR__) . '/app/bootstrap.php';
    require PROJECT_ROOT . '/app/content.php';
    require PROJECT_ROOT . '/app/people.php';
    require PROJECT_ROOT . '/app/accounts.php';
    require PROJECT_ROOT . '/app/media.php';

    if ($command === 'init') {
        db();
        fwrite(STDOUT, "Base SQLite prête.\n");
    } elseif ($command === 'demo') {
        require PROJECT_ROOT . '/app/demo.php';
        seed_demo(db());
        fwrite(STDOUT, "Fiches de démonstration créées.\n");
    } elseif ($command === 'admin' && $argc === 5) {
        $form = ['first_name' => $argv[2], 'last_name' => $argv[3]];
        $firstName = required_text($form, 'first_name', 'Le prénom', 100);
        $lastName = required_text($form, 'last_name', 'Le nom', 100);
        $email = valid_email($argv[4]);
        if (query(db(), 'SELECT 1 FROM users WHERE email = ?', [$email])->fetchColumn()) {
            throw new InputException('Cette adresse est déjà utilisée par un compte.');
        }
        fwrite(STDERR, 'Mot de passe (12 caractères au minimum) : ');
        // Lire le secret sur l'entrée standard pour le garder hors des arguments de la commande.
        // Sur un terminal interactif, rétablir l'écho même si la lecture échoue.
        $terminal = stream_isatty(STDIN);
        if ($terminal) {
            system('stty -echo');
        }
        try {
            $password = rtrim((string) fgets(STDIN), "\r\n");
        } finally {
            if ($terminal) {
                system('stty echo');
                fwrite(STDERR, "\n");
            }
        }
        $hash = secure_password($password);
        query(db(), "INSERT INTO users (first_name,last_name,email,password_hash,state) VALUES (?,?,?,?,'active')",
            [$firstName, $lastName, $email, $hash]);
        fwrite(STDOUT, "Compte administrateur créé.\n");
    } else {
        fwrite($command === 'help' ? STDOUT : STDERR, "php bin/console.php init\n"
            . "php bin/console.php check [--production]\n"
            . "php bin/console.php demo\n"
            . "php bin/console.php admin 'Prénom' 'Nom' 'adresse@example.test'\n");
        exit($command === 'help' ? 0 : 1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
