<?php // Avec JavaScript, le bouton ouvre le sélecteur et le choix du fichier soumet ce formulaire. ?>
<form method="post" action="<?= e(url('personne', ['id' => $personId])) ?>" enctype="multipart/form-data"
    class="upload-box" data-photo-upload="<?= e(PHOTO_UPLOAD_LIMIT) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="kind" value="photo">
    <input type="hidden" name="action" value="upload">
    <div class="stacked-form" data-photo-upload-fallback>
        <label>Photo (10 Mo maximum)
            <input type="file" name="file" required accept=".jpg,.jpeg,.png,.webp,.avif,.heic,.heif,.bmp">
        </label>
        <button class="button button-primary" type="submit"><?= icon('upload', 17) ?> Envoyer la photo</button>
    </div>
    <button class="button button-light" type="button" data-photo-picker hidden><?= icon('plus', 16) ?> Ajouter une photo</button>
    <p class="muted photo-upload-status" data-photo-upload-status role="status"></p>
</form>
