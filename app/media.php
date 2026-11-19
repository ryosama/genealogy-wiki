<?php
declare(strict_types=1);

const PHOTO_FORMATS = [
    'image/jpeg' => 'JPEG', 'image/png' => 'PNG', 'image/webp' => 'WEBP',
    'image/avif' => 'HEIC', 'image/heic' => 'HEIC', 'image/heif' => 'HEIC',
    'image/heic-sequence' => 'HEIC', 'image/heif-sequence' => 'HEIC',
    'image/bmp' => 'BMP', 'image/x-ms-bmp' => 'BMP',
];

function convert_photo(string $source, string $format): string
{
    global $config;
    $target = tempnam($config['data_dir'], 'photo-');
    if (!$target) {
        throw new RuntimeException('Impossible de préparer le fichier de conversion.');
    }
    try {
        // Select the first frame and strip EXIF, ICC and other embedded metadata.
        $command = [$config['image_tool'], '-limit', 'thread', '2', '-limit', 'time', '25', '-limit', 'memory', '128MiB',
            '-limit', 'map', '256MiB', '-limit', 'disk', '512MiB', "$format:$source" . '[0]',
            '-auto-orient', '-resize', '2000x2000', '-strip', '-quality', '84', "WEBP:$target"];
        $process = proc_open($command, [['file', '/dev/null', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('ImageMagick est indisponible.');
        }
        $status = proc_close($process);
        if ($status === 127) {
            throw new RuntimeException('La commande ImageMagick configurée est introuvable.');
        }
        $bytes = file_get_contents($target);
        if ($status !== 0 || strlen($bytes) < 12 || substr($bytes, 8, 4) !== 'WEBP' || strlen($bytes) > MEDIA_LIMIT) {
            throw new InputException('La photo est illisible, ou sa conversion dépasse les limites autorisées.');
        }
        return $bytes;
    } finally {
        unlink($target);
    }
}

function store_media(PDO $db, int $personId, string $kind, string $title, array $file, string $editor): void
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new InputException('Le fichier dépasse la taille autorisée.');
    }
    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new InputException('Choisis un fichier à ajouter.');
    }
    $source = $file['tmp_name'];
    if ($file['size'] < 1 || $file['size'] > MEDIA_LIMIT) {
        throw new InputException('La taille maximale est de 12 Mo par fichier.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($source);
    if ($kind === 'photo' && isset(PHOTO_FORMATS[$mime])) {
        $bytes = convert_photo($source, PHOTO_FORMATS[$mime]);
        $mime = 'image/webp';
    } elseif ($kind === 'document' && in_array($mime, ['application/pdf', 'image/jpeg'], true)) {
        $bytes = file_get_contents($source);
    } else {
        throw new InputException('Type de fichier indisponible. Choisis une photo prise en charge ou un document PDF ou JPEG.');
    }
    if ($title === '') {
        $title = $kind === 'photo' ? 'Photo' : 'Document';
    }
    write_transaction($db, static function (PDO $db) use ($personId, $kind, $title, $mime, $bytes, $editor): void {
        $person = find_person($db, $personId);
        insert_media($db, $personId, $kind, $title, $mime, $bytes);
        record_change($db, $person, $editor, 'media_added', ['Fichier' => $title]);
    });
}

function insert_media(PDO $db, int $personId, string $kind, string $title, string $mime, string $bytes): void
{
    $statement = $db->prepare('INSERT INTO media (person_id,kind,title,mime,bytes) VALUES (?,?,?,?,?)');
    $statement->bindValue(1, $personId, PDO::PARAM_INT);
    $statement->bindValue(2, $kind);
    $statement->bindValue(3, $title);
    $statement->bindValue(4, $mime);
    $statement->bindValue(5, $bytes, PDO::PARAM_LOB);
    $statement->execute();
}

function remove_media(PDO $db, int $personId, int $mediaId, string $editor): void
{
    write_transaction($db, static function (PDO $db) use ($personId, $mediaId, $editor): void {
        $person = find_person($db, $personId);
        $title = query($db, 'SELECT title FROM media WHERE id = ? AND person_id = ?', [$mediaId, $personId])->fetchColumn();
        if ($title === false) {
            throw new InputException('Ce fichier est introuvable.', 404);
        }
        query($db, 'DELETE FROM media WHERE id = ?', [$mediaId]);
        record_change($db, $person, $editor, 'media_removed', ['Fichier' => $title]);
    });
}

function serve_media(PDO $db, int $id): never
{
    $file = query($db, 'SELECT mime,title,bytes FROM media WHERE id = ?', [$id])->fetch();
    if (!$file) {
        throw new InputException('Ce fichier est introuvable.', 404);
    }
    $extension = match ($file['mime']) {
        'image/webp' => 'webp',
        'image/jpeg' => 'jpg',
        'application/pdf' => 'pdf',
        default => throw new RuntimeException('Type de fichier inattendu.'),
    };
    $download = 'fichier.' . $extension;
    $title = rawurlencode(str_replace(["\r", "\n"], '', $file['title']) . '.' . $extension);
    $disposition = ($extension === 'pdf' || isset($_GET['download'])) ? 'attachment' : 'inline';
    header('Content-Type: ' . $file['mime']);
    header("Content-Disposition: $disposition; filename=\"$download\"; filename*=UTF-8''$title");
    header('Content-Length: ' . strlen($file['bytes']));
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        echo $file['bytes'];
    }
    exit;
}
