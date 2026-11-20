<?php
declare(strict_types=1);

// Taille du fichier dérivé et plus petit cadrage proposé sur une photo suffisamment grande.
const PORTRAIT_PIXELS = 256;
const PORTRAIT_MIN_CROP = 32;

function portrait_source(PDO $db, int $personId, int $photoId): array
{
    $photo = query($db, "SELECT id,title,bytes FROM media WHERE id = ? AND person_id = ? AND kind = 'photo'",
        [$photoId, $personId])->fetch();
    if (!$photo) {
        throw new InputException('Cette photo est introuvable sur cette fiche.', 404);
    }
    $dimensions = getimagesizefromstring($photo['bytes']);
    if (!$dimensions || $dimensions['mime'] !== 'image/webp') {
        throw new InputException('Cette photo ne peut pas être utilisée pour un médaillon.');
    }
    return $photo + ['width' => $dimensions[0], 'height' => $dimensions[1]];
}

function portrait_editor_data(PDO $db, int $personId, int $photoId): array
{
    $photo = portrait_source($db, $personId, $photoId);
    unset($photo['bytes']);
    $limit = min($photo['width'], $photo['height']);
    $minimum = min(PORTRAIT_MIN_CROP, $limit);
    // Proposer un carré centré de 65 % du plus petit côté. Un cadrage enregistré est repris tel quel.
    $size = max($minimum, (int) round($limit * 0.65));
    $saved = query($db, 'SELECT crop_x AS x, crop_y AS y, crop_size AS size FROM portraits WHERE person_id = ? AND media_id = ?',
        [$personId, $photoId])->fetch();
    $crop = $saved ?: [
        'x' => intdiv($photo['width'] - $size, 2),
        'y' => intdiv($photo['height'] - $size, 2),
        'size' => $size,
    ];
    return ['photo' => $photo, 'crop' => $crop, 'minimum' => $minimum, 'limit' => $limit, 'saved' => (bool) $saved];
}

function save_portrait(PDO $db, int $personId, int $photoId, array $form, string $editor): void
{
    $photo = portrait_source($db, $personId, $photoId);
    $bounds = ['options' => ['min_range' => 0]];
    $x = filter_var(input($form, 'x'), FILTER_VALIDATE_INT, $bounds);
    $y = filter_var(input($form, 'y'), FILTER_VALIDATE_INT, $bounds);
    $size = filter_var(input($form, 'size'), FILTER_VALIDATE_INT, $bounds);
    $limit = min($photo['width'], $photo['height']);
    // Les coordonnées reçues sont en pixels de la photo orientée, indépendamment de la taille d'affichage.
    if ($x === false || $y === false || $size === false || $size < min(PORTRAIT_MIN_CROP, $limit)
        || $size > $limit || $x > $photo['width'] - $size || $y > $photo['height'] - $size) {
        throw new InputException('Place le cercle entièrement dans la photo et choisis une taille valide.');
    }

    $bytes = crop_portrait_image($photo['bytes'], $x, $y, $size);
    write_transaction($db, static function (PDO $db) use ($personId, $photoId, $x, $y, $size, $bytes, $editor): void {
        $person = find_person($db, $personId);
        // La photo a pu être déplacée ou supprimée pendant le recadrage, effectué hors transaction.
        $title = query($db, "SELECT title FROM media WHERE id = ? AND person_id = ? AND kind = 'photo'", [$photoId, $personId])->fetchColumn();
        if ($title === false) {
            throw new InputException('Cette photo est introuvable sur cette fiche.', 404);
        }
        $statement = $db->prepare(<<<'SQL'
            INSERT INTO portraits (person_id, media_id, crop_x, crop_y, crop_size, bytes) VALUES (?,?,?,?,?,?)
            ON CONFLICT(person_id) DO UPDATE SET
                media_id = excluded.media_id, crop_x = excluded.crop_x, crop_y = excluded.crop_y,
                crop_size = excluded.crop_size, bytes = excluded.bytes
            SQL);
        foreach ([$personId, $photoId, $x, $y, $size] as $index => $value) {
            $statement->bindValue($index + 1, $value, PDO::PARAM_INT);
        }
        $statement->bindValue(6, $bytes, PDO::PARAM_LOB);
        $statement->execute();
        record_change($db, $person, $editor, 'portrait_saved', ['Fichier' => $title]);
    });
}

function remove_portrait(PDO $db, int $personId, int $photoId, string $editor): bool
{
    return write_transaction($db, static function (PDO $db) use ($personId, $photoId, $editor): bool {
        $person = find_person($db, $personId);
        // Vérifier la photo affichée dans le formulaire au cas où un autre éditeur aurait choisi un nouveau médaillon.
        $title = query($db, 'SELECT m.title FROM portraits p JOIN media m ON m.id = p.media_id
            WHERE p.person_id = ? AND p.media_id = ?', [$personId, $photoId])->fetchColumn();
        if ($title === false) {
            return false;
        }
        query($db, 'DELETE FROM portraits WHERE person_id = ?', [$personId]);
        record_change($db, $person, $editor, 'portrait_removed', ['Fichier' => $title]);
        return true;
    });
}

function crop_portrait_image(string $bytes, int $x, int $y, int $size): string
{
    global $config;
    $source = tempnam($config['data_dir'], 'portrait-');
    if (!$source) {
        throw new RuntimeException('Impossible de préparer le recadrage.');
    }
    try {
        if (file_put_contents($source, $bytes) !== strlen($bytes)) {
            throw new RuntimeException('Impossible de lire la photo pour le recadrage.');
        }
        $geometry = sprintf('%dx%d+%d+%d', $size, $size, $x, $y);
        $resolution = PORTRAIT_PIXELS . 'x' . PORTRAIT_PIXELS;
        // Stocker un carré autonome léger. L'arbre et les listes appliquent le masque circulaire.
        return render_webp(["WEBP:$source", '-crop', $geometry, '+repage', '-resize', $resolution]);
    } finally {
        unlink($source);
    }
}
