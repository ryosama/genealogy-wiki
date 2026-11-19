<details class="upload-box">
    <summary><?= $kind === 'photo' ? 'Ajouter une photo' : 'Ajouter un document' ?></summary>
    <form method="post" action="<?= e(url('personne', ['id' => $personId])) ?>" enctype="multipart/form-data" class="stacked-form">
        <?= csrf_field() ?>
        <input type="hidden" name="kind" value="<?= e($kind) ?>">
        <label>Titre <input name="title" maxlength="180"></label>
        <label>Fichier (12 Mo maximum)
            <input type="file" name="file" required accept="<?= $kind === 'photo' ? '.jpg,.jpeg,.png,.webp,.avif,.heic,.heif,.bmp' : '.pdf,.jpg,.jpeg' ?>">
        </label>
        <button class="button button-primary" name="action" value="upload">Envoyer</button>
    </form>
</details>
