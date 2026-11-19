<?php
declare(strict_types=1);

return [
    // URL publique de base, sans barre oblique finale.
    'app_url' => 'http://127.0.0.1:8000',

    // Les messages de développement sont écrits dans data_dir/outbox.
    // Utiliser 'mail' avec un agent d’envoi configuré pour PHP en production.
    'mail_mode' => 'outbox',
    'mail_from' => '',

    // Utiliser 'magick' avec ImageMagick 7 et 'convert' avec ImageMagick 6.
    'image_tool' => 'convert',

    // Par défaut, les données sont dans le dossier var du projet.
    // 'data_dir' => '/var/lib/genealogy-wiki',
];
