<?php
// Les administrateurs retrouvent le formulaire sur la fiche que consultent les visiteurs.
$canEdit = $user !== null;
$profileUrl = url('personne', $person ? ['id' => $person['id']] : []);
$saveLabel = $person ? 'Enregistrer les modifications' : 'Créer la fiche';
?>
<section class="container page-space profile-page<?= $person ? '' : ' profile-create edit-page' ?>">
    <a class="text-link back-link" href="<?= e(url('personnes')) ?>"><?= icon('left', 17) ?> Toutes les personnes</a>
    <div class="profile-heading">
        <?php if ($person): ?>
            <div class="profile-portrait">
                <?= avatar($person, $photos[0]['id'] ?? null, 'profile-avatar') ?>
                <?php // Un administrateur peut retirer le cadrage enregistré depuis l'avatar de la fiche.
                if ($canEdit && $person['portrait_id']): ?>
                    <?php view('remove-portrait', [
                        'actionUrl' => $profileUrl, 'photoId' => $person['portrait_id'], 'iconOnly' => true,
                    ]); ?>
                <?php endif ?>
            </div>
        <?php endif ?>
        <div class="profile-title">
            <h1><?= e($title) ?></h1>
            <?php if ($person): ?>
                <p><?= icon('calendar', 18) ?> <?= e(lifespan($person)) ?>
                    <?php if ($person['residence']): ?>
                        <span class="profile-location"><?= icon('location', 18) ?> <?= e($person['residence']) ?></span>
                    <?php endif ?>
                </p>
            <?php else: ?>
                <p>Les champs laissés vides seront ajoutés à la liste « À renseigner ultérieurement ».</p>
            <?php endif ?>
        </div>
        <div class="actions profile-actions">
            <?php if ($person): ?><?= print_button('Imprimer la fiche') ?><?php endif ?>
            <?php if ($canEdit): ?>
                <button class="button button-primary" type="submit" form="profile-fields"><?= icon($person ? 'floppy-disk' : 'plus', 17) ?> <?= e($saveLabel) ?></button>
            <?php endif ?>
        </div>
    </div>

    <div class="profile-grid">
        <div class="profile-column">
            <?php view('person-details', compact('person', 'values', 'canEdit', 'profileUrl')); ?>

            <?php if ($person): ?>
                <section class="panel content-panel" aria-labelledby="documents-heading">
                    <h2 id="documents-heading"><?= icon('file', 20) ?> Documents</h2>
                    <?php if (!$documents): ?><p class="muted">Aucun document.</p><?php endif ?>
                    <div class="files">
                        <?php // Chaque document est téléchargeable par tous et peut être retiré par un administrateur.
                        foreach ($documents as $document): ?>
                            <div class="file-row">
                                <a href="<?= e(url('media', ['id' => $document['id'], 'download' => 1])) ?>">
                                    <?= icon('download', 18) ?> <span><?= e($document['title']) ?></span>
                                    <small><?= $document['mime'] === 'application/pdf' ? 'PDF' : 'JPEG' ?></small>
                                </a>
                                <?php // Le bouton de retrait cible le document de cette ligne.
                                if ($user): view('remove-media', ['personId' => $person['id'], 'mediaId' => $document['id']]); endif ?>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <?php // Afficher le formulaire d'ajout de document pour les administrateurs, même si la liste est vide.
                    if ($user): view('upload', ['personId' => $person['id'], 'kind' => 'document']); endif ?>
                </section>
            <?php endif ?>
        </div>

        <?php // Les relations et les fichiers s'ajoutent dès que la fiche a été créée.
        if ($person): ?>
            <div class="profile-column">
                <section class="panel content-panel" aria-labelledby="relationships-heading">
                    <h2 id="relationships-heading"><?= icon('people', 21) ?> Relations</h2>
                    <?php if (!array_filter($groups)): ?><p class="muted">Aucune relation renseignée.</p><?php endif ?>
                    <div class="relation-groups">
                        <?php // Présenter les proches par catégorie et conserver le même ordre pour tous les lecteurs.
                        foreach (['parents' => 'Parents', 'partners' => 'Conjoints', 'children' => 'Enfants', 'siblings' => 'Frères et sœurs'] as $key => $label): ?>
                            <?php if (!$groups[$key]) { continue; } ?>
                            <div>
                                <h3><?= e($label) ?></h3>
                                <ul>
                                    <?php // Chaque proche a un lien public vers sa fiche. Les liens enregistrés peuvent être édités.
                                    foreach ($groups[$key] as $relative): ?>
                                        <li>
                                            <a class="relative-person" href="<?= e(url('personne', ['id' => $relative['id']])) ?>">
                                                <?= sex_icon($relative['sex']) ?>
                                                <span><?= e(full_name($relative)) ?></span>
                                            </a>
                                            <?php // Une relation enregistrée fournit le type et l'identifiant que la fenêtre de modification utilise.
                                            if (isset($relative['kind'])): ?>
                                                <small><?= e(RELATION_LABELS[$relative['kind']]) ?><?= $relative['end_year'] ? e(' · fin en ' . $relative['end_year']) : '' ?></small>
                                                <?php // Un administrateur peut ouvrir cette relation dans le formulaire de l'arbre.
                                                if ($user): ?>
                                                    <button class="icon-button relation-edit" data-edit-relation="<?= e($relative['relation_id']) ?>"
                                                        type="button" title="Modifier cette relation" aria-label="<?= e('Modifier la relation avec ' . full_name($relative)) ?>">
                                                        <?= icon('edit', 15) ?>
                                                    </button>
                                                <?php endif ?>
                                            <?php endif ?>
                                        </li>
                                    <?php endforeach ?>
                                </ul>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <?php // Permettre aux administrateurs d'ouvrir le formulaire de création d'une relation depuis la fiche.
                    if ($user): ?><button class="button button-light panel-action" id="person-relations" type="button" data-new-relation data-task-field><?= icon('plus', 17) ?> Ajouter une relation</button><?php endif ?>
                </section>

                <section class="panel content-panel" aria-labelledby="photos-heading">
                    <h2 id="photos-heading"><?= icon('photo', 20) ?> Photos</h2>
                    <?php if (!$photos): ?><p class="muted">Aucune photo.</p><?php endif ?>
                    <?php if ($photos): ?>
                        <div class="photo-grid">
                            <?php // Afficher les originaux à tous les lecteurs et ajouter des commandes par photo pour les administrateurs.
                            foreach ($photos as $photo): ?>
                                <figure>
                                    <a class="photo-original" href="<?= e(url('media', ['id' => $photo['id']])) ?>">
                                        <img src="<?= e(url('media', ['id' => $photo['id']])) ?>" alt="<?= e($photo['title']) ?>" loading="lazy">
                                    </a>
                                    <figcaption><?= e($photo['title']) ?></figcaption>
                                    <?php // L'éditeur regroupe le titre, le recadrage de la photo et le médaillon.
                                    // Le formulaire de suppression cible la photo elle-même.
                                    if ($user): ?>
                                        <a class="text-link photo-portrait-link" href="<?= e(url('photo', ['id' => $person['id'], 'photo' => $photo['id']])) ?>">
                                            <?= icon('edit', 15) ?> Modifier la photo
                                        </a>
                                        <?php view('remove-media', ['personId' => $person['id'], 'mediaId' => $photo['id']]); ?>
                                    <?php endif ?>
                                </figure>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                    <?php // Afficher le formulaire d'ajout de photo pour les administrateurs, même si la galerie est vide.
                    if ($user): view('photo-upload', ['personId' => $person['id']]); endif ?>
                </section>
            </div>
        <?php endif ?>
    </div>

    <?php if ($person): view('graph', get_defined_vars()); endif ?>

    <?php // Les administrateurs peuvent consulter l'historique de cette fiche.
    if ($person && $canEdit): ?>
        <section class="profile-admin">
            <a class="text-link" href="<?= e(url('historique', ['id' => $person['id']])) ?>"><?= icon('activity', 18) ?> Historique de la fiche</a>
            <details class="admin-tools">
                <summary>Gestion de la fiche</summary>
                <?php // Proposer la fusion lorsqu'une autre fiche peut servir de source à la fiche affichée.
                if ($duplicates): ?>
                    <form method="post" action="<?= e(url('personne', ['id' => $person['id']])) ?>" class="stacked-form"
                        data-confirm="Fusionner ces deux fiches ? Cette action supprime la fiche sélectionnée.">
                        <?= csrf_field() ?>
                        <label>Récupérer les données manquantes, les médias et les relations d’une autre fiche
                            <select name="duplicate_id" required>
                                <option value="">Choisir un doublon</option>
                                <?php // Indiquer l'année de naissance si elle est connue pour distinguer les homonymes.
                                foreach ($duplicates as $duplicate): ?>
                                    <option value="<?= e($duplicate['id']) ?>"><?= e(full_name($duplicate) . ($duplicate['birth_year'] ? ' · ' . $duplicate['birth_year'] : '')) ?></option>
                                <?php endforeach ?>
                            </select>
                        </label>
                        <button class="button button-light" name="action" value="merge-person"><?= icon('code-merge', 17) ?> Fusionner les fiches</button>
                    </form>
                <?php endif ?>
                <form method="post" action="<?= e(url('personne', ['id' => $person['id']])) ?>"
                    data-confirm="Supprimer définitivement la fiche, ses relations et ses fichiers ?">
                    <?= csrf_field() ?>
                    <button class="button button-danger" name="action" value="delete-person"><?= icon('trash', 17) ?> Supprimer la fiche</button>
                </form>
            </details>
        </section>
    <?php endif ?>

    <?php if ($canEdit): ?>
        <div class="actions profile-actions profile-footer-actions">
            <button class="button button-primary" type="submit" form="profile-fields"><?= icon($person ? 'floppy-disk' : 'plus', 17) ?> <?= e($saveLabel) ?></button>
        </div>
    <?php endif ?>
</section>
