<?php
// Les administrateurs enregistrent le titre, le cadre et le médaillon avec le même formulaire.
$profile = url('personne', ['id' => $person['id']]);
$photoUrl = url('media', ['id' => $photo['id']]);
$editorUrl = url('photo', ['id' => $person['id'], 'photo' => $photo['id']]);
?>
<section class="container page-space portrait-page">
    <a class="text-link back-link" href="<?= e($profile) ?>"><?= icon('left', 17) ?> Revenir à la fiche</a>
    <div class="page-heading">
        <div>
            <h1><?= e($title) ?></h1>
            <p>Pour <strong><?= e(full_name($person)) ?></strong></p>
        </div>
    </div>

    <form id="photo-edit-form" action="<?= e($editorUrl) ?>" method="post" data-photo-editor
        data-portrait-minimum="<?= e($portraitMinimum) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save-photo">
        <input type="hidden" name="revision" value="<?= e($revision) ?>">
        <input type="hidden" name="mode" value="<?= e($mode) ?>">
        <div class="photo-editor-heading panel">
            <label>Titre de la photo <input name="title" value="<?= e($photo['title']) ?>" maxlength="180"></label>
            <div class="actions photo-editor-tools" role="group" aria-label="Outil de cadrage" data-photo-tools hidden>
                <button class="button button-light" type="button" data-photo-mode="frame" aria-pressed="false"><?= icon('crop-simple', 17) ?> Recadrer la photo</button>
                <button class="button button-light" type="button" data-photo-mode="portrait" aria-pressed="false"><?= icon('circle', 17) ?> Médaillon</button>
            </div>
        </div>
        <div class="panel portrait-workspace" data-photo-workspace>
            <div class="portrait-photo-pane">
                <div class="portrait-stage">
                    <img src="<?= e($photoUrl) ?>" width="<?= e($photo['width']) ?>" height="<?= e($photo['height']) ?>"
                        alt="<?= e($photo['title']) ?>" data-photo-source>
                    <div class="photo-frame" data-photo-frame hidden aria-hidden="true">
                        <?php // Les quatre poignées modifient le rectangle sans imposer de rapport largeur/hauteur.
                        foreach (['nw', 'ne', 'sw', 'se'] as $corner): ?>
                            <span class="photo-frame-handle photo-frame-<?= e($corner) ?>" data-photo-resize="<?= e($corner) ?>"></span>
                        <?php endforeach ?>
                    </div>
                    <div class="portrait-selection" data-portrait-frame hidden aria-hidden="true">
                        <span class="portrait-resize" data-photo-resize="circle"></span>
                    </div>
                </div>
                <noscript><p>Active JavaScript pour déplacer le cadre ou le cercle.</p></noscript>
            </div>
            <aside class="portrait-controls" data-photo-controls hidden>
                <div class="stacked-form" data-photo-panel="frame">
                    <h2>Cadre de la photo</h2>
                    <p class="muted">Déplace le cadre et ajuste ses coins. Seule la zone encadrée sera conservée après l’enregistrement.</p>
                    <output class="photo-dimensions" data-photo-dimensions></output>
                    <label>Largeur du cadre
                        <input type="range" name="frame_width" min="1" max="<?= e($photo['width']) ?>" value="<?= e($frame['width']) ?>">
                    </label>
                    <label>Hauteur du cadre
                        <input type="range" name="frame_height" min="1" max="<?= e($photo['height']) ?>" value="<?= e($frame['height']) ?>">
                    </label>
                    <label>Position horizontale du cadre
                        <input type="range" name="frame_x" min="0" max="<?= e($photo['width'] - $frame['width']) ?>" value="<?= e($frame['x']) ?>">
                    </label>
                    <label>Position verticale du cadre
                        <input type="range" name="frame_y" min="0" max="<?= e($photo['height'] - $frame['height']) ?>" value="<?= e($frame['y']) ?>">
                    </label>
                    <button class="button button-light button-small" type="button" data-reset-frame><?= icon('rotate-left', 16) ?> Rétablir la photo entière</button>
                    <p class="muted" data-portrait-adjusted role="status" hidden>Le recadrage a ajusté le médaillon. Vérifie son aperçu.</p>
                </div>
                <div class="stacked-form" data-photo-panel="portrait" hidden>
                    <h2>Médaillon</h2>
                    <div class="portrait-preview" aria-hidden="true">
                        <img src="<?= e($photoUrl) ?>" alt="" data-portrait-preview>
                    </div>
                    <label class="photo-portrait-choice">
                        <input type="checkbox" name="use_portrait" value="1" <?= $usePortrait ? 'checked' : '' ?>> Utiliser comme médaillon
                    </label>
                    <label>Taille du médaillon
                        <input type="range" name="size" min="<?= e(min($portraitMinimum, $frame['width'], $frame['height'])) ?>"
                            max="<?= e(min($frame['width'], $frame['height'])) ?>" value="<?= e($crop['size']) ?>">
                    </label>
                    <label>Position horizontale du médaillon
                        <input type="range" name="x" min="<?= e($frame['x']) ?>" max="<?= e($frame['x'] + $frame['width'] - $crop['size']) ?>" value="<?= e($crop['x']) ?>">
                    </label>
                    <label>Position verticale du médaillon
                        <input type="range" name="y" min="<?= e($frame['y']) ?>" max="<?= e($frame['y'] + $frame['height'] - $crop['size']) ?>" value="<?= e($crop['y']) ?>">
                    </label>
                </div>
            </aside>
        </div>
    </form>
    <div class="actions portrait-actions">
        <button class="button button-primary" type="submit" form="photo-edit-form"><?= icon('floppy-disk', 17) ?> Enregistrer</button>
        <a class="button button-danger-filled" href="<?= e($profile) ?>"><?= icon('circle-xmark', 17) ?> Annuler les modifications</a>
        <?php // Le retrait immédiat reste disponible pour le médaillon déjà enregistré sur cette photo.
        if ($saved): view('remove-portrait', ['actionUrl' => $editorUrl, 'photoId' => $photo['id']]); endif ?>
    </div>
</section>
