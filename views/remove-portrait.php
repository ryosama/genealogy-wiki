<?php
// Envoyer le retrait séparément de l'enregistrement de la fiche ou du cadrage.
$iconOnly = $iconOnly ?? false;
?>
<form class="inline-form remove-portrait" action="<?= e($actionUrl) ?>" method="post"
    data-confirm="Retirer le médaillon ? La photo d’origine sera conservée.">
    <?= csrf_field() ?>
    <input type="hidden" name="media_id" value="<?= e($photoId) ?>">
    <button class="<?= $iconOnly ? 'icon-button' : 'button button-danger' ?>" type="submit" name="action" value="delete-portrait"
        <?php if ($iconOnly): ?>title="Retirer le médaillon" aria-label="Retirer le médaillon"<?php endif ?>>
        <?= icon('close', 16) ?><?php if (!$iconOnly): ?> Retirer le médaillon<?php endif ?>
    </button>
</form>
