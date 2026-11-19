<form class="remove-media inline-form" method="post" action="<?= e(url('personne', ['id' => $personId])) ?>"
    data-confirm="Supprimer définitivement ce fichier ?">
    <?= csrf_field() ?>
    <input type="hidden" name="media_id" value="<?= e($mediaId) ?>">
    <button class="icon-button" name="action" value="delete-media" title="Supprimer le fichier" aria-label="Supprimer le fichier"><?= icon('close', 16) ?></button>
</form>
