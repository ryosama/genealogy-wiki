<?php // Les administrateurs utilisent cet écran pour cadrer une photo de la fiche. ?>
<section class="container page-space portrait-page">
    <?php $profile = url('personne', ['id' => $person['id']]); ?>
    <a class="text-link back-link" href="<?= e($profile) ?>"><?= icon('left', 17) ?> Revenir à la fiche</a>
    <div class="page-heading">
        <div>
            <h1><?= e($title) ?></h1>
            <p>Pour <strong><?= e(full_name($person)) ?></strong></p>
        </div>
    </div>

    <?php $photoUrl = url('media', ['id' => $photo['id']]); ?>
    <?php $cropUrl = url('medaillon', ['id' => $person['id'], 'photo' => $photo['id']]); ?>
    <form id="portrait-crop-form" action="<?= e($cropUrl) ?>" method="post" data-portrait-editor>
        <?= csrf_field() ?>
        <div class="panel portrait-workspace">
            <div class="portrait-photo-pane">
                <p data-crop-instructions hidden>Fais glisser le cercle sur le visage et ajuste sa taille avec la poignée ou le curseur.</p>
                <div class="portrait-stage">
                    <img src="<?= e($photoUrl) ?>" width="<?= e($photo['width']) ?>" height="<?= e($photo['height']) ?>"
                        alt="<?= e($photo['title']) ?>" data-crop-photo>
                    <div class="portrait-selection" data-crop-selection hidden aria-hidden="true">
                        <span class="portrait-resize" data-crop-resize></span>
                    </div>
                </div>
                <noscript><p>Le bouton enregistre le cadrage proposé. Active JavaScript pour ajuster le cercle.</p></noscript>
            </div>
            <div class="portrait-controls" data-crop-controls hidden>
                <h2>Aperçu</h2>
                <div class="portrait-preview" aria-hidden="true">
                    <img src="<?= e($photoUrl) ?>" alt="" data-crop-preview>
                </div>
                <div class="stacked-form">
                    <label>Taille du médaillon
                        <input type="range" name="size" min="<?= e($minimum) ?>" max="<?= e($limit) ?>" value="<?= e($crop['size']) ?>">
                    </label>
                    <label>Position horizontale
                        <input type="range" name="x" min="0" max="<?= e($photo['width'] - $crop['size']) ?>" value="<?= e($crop['x']) ?>">
                    </label>
                    <label>Position verticale
                        <input type="range" name="y" min="0" max="<?= e($photo['height'] - $crop['size']) ?>" value="<?= e($crop['y']) ?>">
                    </label>
                </div>
            </div>
        </div>
    </form>
    <div class="actions portrait-actions">
        <button class="button button-primary" form="portrait-crop-form" name="action" value="save-portrait">Enregistrer le médaillon</button>
        <a class="button button-light" href="<?= e($profile) ?>"><?= $saved ? 'Annuler' : 'Plus tard' ?></a>
        <?php // Permettre le retrait lorsque cette photo est la source du médaillon enregistré.
        if ($saved): view('remove-portrait', ['actionUrl' => $cropUrl, 'photoId' => $photo['id']]); endif ?>
    </div>
</section>
