<?php
declare(strict_types=1);

// Taille du fichier dérivé et plus petit médaillon sur une photo suffisamment grande.
const PORTRAIT_PIXELS = 256;
const PORTRAIT_MIN_CROP = 32;

function photo_source(PDO $db, int $personId, int $photoId): array
{
    $photo = query($db, <<<'SQL'
        SELECT m.id, m.title, m.bytes, p.media_id AS portrait_id, p.crop_x, p.crop_y, p.crop_size
        FROM media m LEFT JOIN portraits p ON p.person_id = m.person_id
        WHERE m.id = ? AND m.person_id = ? AND m.kind = 'photo'
        SQL, [$photoId, $personId])->fetch();
    if (!$photo) {
        throw new InputException('Cette photo est introuvable sur cette fiche.', 404);
    }
    $dimensions = getimagesizefromstring($photo['bytes']);
    if (!$dimensions || $dimensions['mime'] !== 'image/webp') {
        throw new InputException('Cette photo ne peut pas être modifiée.');
    }
    return $photo + ['width' => $dimensions[0], 'height' => $dimensions[1]];
}

function photo_revision(array $photo): string
{
    // Les coordonnées du formulaire dépendent de cette version de la photo et du médaillon actif.
    return hash('sha256', serialize($photo));
}

function saved_photo_portrait(array $photo): ?array
{
    return $photo['portrait_id'] === $photo['id']
        ? ['x' => $photo['crop_x'], 'y' => $photo['crop_y'], 'size' => $photo['crop_size']] : null;
}

function full_photo_frame(array $photo): array
{
    return ['x' => 0, 'y' => 0, 'width' => $photo['width'], 'height' => $photo['height']];
}

function default_portrait_crop(array $frame): array
{
    // Proposer un carré centré de 65 % du petit côté de la zone conservée.
    $limit = min($frame['width'], $frame['height']);
    $size = max(min(PORTRAIT_MIN_CROP, $limit), (int) round($limit * 0.65));
    return [
        'x' => $frame['x'] + intdiv($frame['width'] - $size, 2),
        'y' => $frame['y'] + intdiv($frame['height'] - $size, 2),
        'size' => $size,
    ];
}

function crop_coordinates(array $form, array $fields): array
{
    $coordinates = [];
    foreach ($fields as $key => $field) {
        $coordinates[$key] = filter_var(input($form, $field), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    }
    return $coordinates;
}

function read_photo_frame(array $form, array $photo): array
{
    $frame = crop_coordinates($form, ['x' => 'frame_x', 'y' => 'frame_y', 'width' => 'frame_width', 'height' => 'frame_height']);
    if (in_array(false, $frame, true) || $frame['width'] < 1 || $frame['height'] < 1
        || $frame['x'] > $photo['width'] - $frame['width'] || $frame['y'] > $photo['height'] - $frame['height']) {
        throw new InputException('Place le cadre entièrement dans la photo.');
    }
    return $frame;
}

function read_portrait_crop(array $form, array $frame): array
{
    $crop = crop_coordinates($form, ['x' => 'x', 'y' => 'y', 'size' => 'size']);
    $limit = min($frame['width'], $frame['height']);
    // Le carré du médaillon doit rester dans la portion de la photo enregistrée.
    if (in_array(false, $crop, true) || $crop['size'] < min(PORTRAIT_MIN_CROP, $limit) || $crop['size'] > $limit
        || $crop['x'] < $frame['x'] || $crop['y'] < $frame['y']
        || $crop['x'] > $frame['x'] + $frame['width'] - $crop['size']
        || $crop['y'] > $frame['y'] + $frame['height'] - $crop['size']) {
        throw new InputException('Place le médaillon entièrement à l’intérieur du cadre de la photo.');
    }
    return $crop;
}

function photo_editor_data(PDO $db, int $personId, int $photoId, bool $openPortrait, array $draft): array
{
    $photo = photo_source($db, $personId, $photoId);
    $revision = photo_revision($photo);
    $saved = saved_photo_portrait($photo);
    $frame = full_photo_frame($photo);
    $crop = $saved ?? default_portrait_crop($frame);
    $usePortrait = $openPortrait || $saved !== null;
    $mode = $openPortrait ? 'portrait' : 'frame';

    // Reprendre la saisie après un refus, si les coordonnées concernent toujours la photo affichée.
    if (input($draft, 'revision') === $revision) {
        $photo['title'] = input($draft, 'title');
        $usePortrait = input($draft, 'use_portrait') === '1';
        $mode = input($draft, 'mode') === 'portrait' ? 'portrait' : 'frame';
        try {
            $frame = read_photo_frame($draft, $photo);
            $crop = $usePortrait ? read_portrait_crop($draft, $frame) : default_portrait_crop($frame);
        } catch (InputException) {
            $crop = default_portrait_crop($frame);
        }
    }
    unset($photo['bytes']);
    return ['photo' => $photo, 'revision' => $revision, 'frame' => $frame, 'crop' => $crop,
        'saved' => $saved !== null, 'usePortrait' => $usePortrait, 'mode' => $mode, 'portraitMinimum' => PORTRAIT_MIN_CROP];
}

function assert_photo_revision(array $photo, string $revision): void
{
    if ($revision !== photo_revision($photo)) {
        throw new InputException('La photo ou son médaillon a changé. Vérifie le cadrage affiché avant d’enregistrer.', 409);
    }
}

function save_photo_edit(PDO $db, int $personId, int $photoId, array $form, string $editor): void
{
    $photo = photo_source($db, $personId, $photoId);
    $revision = input($form, 'revision');
    assert_photo_revision($photo, $revision);

    $title = short_text($form, 'title', 180);
    $title = $title === '' ? 'Photo' : $title;
    $frame = read_photo_frame($form, $photo);
    $cropped = $frame !== full_photo_frame($photo);
    $saved = saved_photo_portrait($photo);
    $circle = input($form, 'use_portrait') === '1' ? read_portrait_crop($form, $frame) : null;
    $removePortrait = $circle === null && $saved !== null;

    // Préparer les deux WebP avant la transaction. Le cercle est choisi dans les pixels de la source.
    $photoBytes = $cropped ? crop_photo_webp($photo['bytes'], $frame) : null;
    $portraitBytes = null;
    if ($circle !== null && ($circle !== $saved || $cropped)) {
        $square = ['x' => $circle['x'], 'y' => $circle['y'], 'width' => $circle['size'], 'height' => $circle['size']];
        $portraitBytes = crop_photo_webp($photo['bytes'], $square, PORTRAIT_PIXELS);
    }

    write_transaction($db, static function (PDO $db) use (
        $personId, $photoId, $title, $frame, $circle, $photoBytes, $portraitBytes, $removePortrait, $revision, $editor
    ): void {
        // Un autre administrateur a pu modifier la photo pendant la conversion.
        $current = photo_source($db, $personId, $photoId);
        assert_photo_revision($current, $revision);
        $person = find_person($db, $personId);

        if ($photoBytes !== null) {
            $update = $db->prepare('UPDATE media SET title = ?, bytes = ? WHERE id = ?');
            $update->bindValue(1, $title);
            $update->bindValue(2, $photoBytes, PDO::PARAM_LOB);
            $update->bindValue(3, $photoId, PDO::PARAM_INT);
            $update->execute();
        } elseif ($current['title'] !== $title) {
            query($db, 'UPDATE media SET title = ? WHERE id = ?', [$title, $photoId]);
        }
        if ($photoBytes !== null || $current['title'] !== $title) {
            record_change($db, $person, $editor, 'photo_edited', ['Fichier' => $title]);
        }

        if ($portraitBytes !== null) {
            // Recaler le cercle dans le WebP final, auquel se référera une édition ultérieure.
            write_photo_portrait($db, $personId, $photoId, $circle['x'] - $frame['x'], $circle['y'] - $frame['y'],
                $circle['size'], $portraitBytes);
            record_change($db, $person, $editor, 'portrait_saved', ['Fichier' => $title]);
        } elseif ($removePortrait) {
            query($db, 'DELETE FROM portraits WHERE person_id = ? AND media_id = ?', [$personId, $photoId]);
            record_change($db, $person, $editor, 'portrait_removed', ['Fichier' => $title]);
        }
    });
}

function save_portrait(PDO $db, int $personId, int $photoId, array $form, string $editor): void
{
    // Ancien formulaire du médaillon. Conserver le titre et utiliser la photo entière comme cadre.
    $photo = photo_source($db, $personId, $photoId);
    save_photo_edit($db, $personId, $photoId, [
        'title' => $photo['title'], 'revision' => photo_revision($photo), 'use_portrait' => '1',
        'frame_x' => '0', 'frame_y' => '0', 'frame_width' => (string) $photo['width'], 'frame_height' => (string) $photo['height'],
        'x' => input($form, 'x'), 'y' => input($form, 'y'), 'size' => input($form, 'size'),
    ], $editor);
}

function write_photo_portrait(PDO $db, int $personId, int $photoId, int $x, int $y, int $size, string $bytes): void
{
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

function crop_photo_webp(string $bytes, array $frame, ?int $resize = null): string
{
    global $config;
    $source = tempnam($config['data_dir'], 'crop-');
    if (!$source) {
        throw new RuntimeException('Impossible de préparer le recadrage.');
    }
    try {
        if (file_put_contents($source, $bytes) !== strlen($bytes)) {
            throw new RuntimeException('Impossible de lire la photo pour le recadrage.');
        }
        $geometry = sprintf('%dx%d+%d+%d', $frame['width'], $frame['height'], $frame['x'], $frame['y']);
        $arguments = ["WEBP:$source", '-crop', $geometry, '+repage'];
        if ($resize !== null) {
            $arguments = [...$arguments, '-resize', $resize . 'x' . $resize];
        }
        return render_webp($arguments);
    } finally {
        unlink($source);
    }
}
