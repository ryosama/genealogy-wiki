<section class="container page-space profile-page">
    <a class="text-link back-link" href="<?= e(url('personnes')) ?>"><?= icon('left', 17) ?> Toutes les personnes</a>
    <div class="profile-heading">
        <?= avatar($person, $photos[0]['id'] ?? null, 'profile-avatar') ?>
        <div class="profile-title">
            <h1><?= e(full_name($person)) ?></h1>
            <p><?= icon('calendar', 18) ?> <?= e(lifespan($person)) ?>
                <?php if ($person['residence']): ?>
                    <span class="profile-location"><?= icon('location', 18) ?> <?= e($person['residence']) ?></span>
                <?php endif ?>
            </p>
        </div>
        <div class="actions profile-actions">
            <?= print_button('Imprimer la fiche') ?>
            <?php if ($user): ?>
                <a class="button button-primary" href="<?= e(url('edition', ['id' => $person['id']])) ?>"><?= icon('edit', 18) ?> Modifier la fiche</a>
            <?php endif ?>
        </div>
    </div>

    <div class="profile-grid">
        <div class="profile-column">
            <section class="panel content-panel" aria-labelledby="bio-heading">
                <h2 id="bio-heading"><?= icon('book', 20) ?> Biographie</h2>
                <dl class="life-dates">
                    <?php foreach (['birth' => 'Naissance', 'death' => 'Décès'] as $key => $label): ?>
                        <?php $date = $person[$key . '_date']; $place = $person[$key . '_place']; ?>
                        <div><dt><?= e($label) ?></dt><dd><?= e(implode(' · ', array_filter([$date, $place])) ?: 'Non renseigné') ?></dd></div>
                    <?php endforeach ?>
                    <?php if ($person['residence']): ?>
                        <div><dt>Lieu de vie</dt><dd><?= e($person['residence']) ?></dd></div>
                    <?php endif ?>
                </dl>
                <?php if ($person['biography']): ?>
                    <div class="formatted"><?= $person['biography'] ?></div>
                <?php endif ?>
            </section>

            <section class="panel content-panel" aria-labelledby="facts-heading">
                <h2 id="facts-heading"><?= icon('activity', 21) ?> Faits notables</h2>
                <?php if ($person['notable_facts']): ?>
                    <div class="formatted"><?= $person['notable_facts'] ?></div>
                <?php else: ?>
                    <p class="muted">Aucun fait notable renseigné.</p>
                <?php endif ?>
            </section>

            <section class="panel content-panel" aria-labelledby="documents-heading">
                <h2 id="documents-heading"><?= icon('file', 20) ?> Documents</h2>
                <?php if (!$documents): ?><p class="muted">Aucun document.</p><?php endif ?>
                <div class="files">
                    <?php foreach ($documents as $document): ?>
                        <div class="file-row">
                            <a href="<?= e(url('media', ['id' => $document['id'], 'download' => 1])) ?>">
                                <?= icon('download', 18) ?> <span><?= e($document['title']) ?></span>
                                <small><?= $document['mime'] === 'application/pdf' ? 'PDF' : 'JPEG' ?></small>
                            </a>
                            <?php if ($user): view('remove-media', ['personId' => $person['id'], 'mediaId' => $document['id']]); endif ?>
                        </div>
                    <?php endforeach ?>
                </div>
                <?php if ($user): view('upload', ['personId' => $person['id'], 'kind' => 'document']); endif ?>
            </section>
        </div>

        <div class="profile-column">
            <section class="panel content-panel" aria-labelledby="relationships-heading">
                <h2 id="relationships-heading"><?= icon('people', 21) ?> Relations</h2>
                <?php if (!array_filter($groups)): ?><p class="muted">Aucune relation renseignée.</p><?php endif ?>
                <div class="relation-groups">
                    <?php foreach (['parents' => 'Parents', 'partners' => 'Conjoints', 'children' => 'Enfants', 'siblings' => 'Frères et sœurs'] as $key => $label): ?>
                        <?php if (!$groups[$key]) { continue; } ?>
                        <div>
                            <h3><?= e($label) ?></h3>
                            <ul>
                                <?php foreach ($groups[$key] as $relative): ?>
                                    <li>
                                        <a href="<?= e(url('personne', ['id' => $relative['id']])) ?>"><?= e(full_name($relative)) ?></a>
                                        <?php if (isset($relative['kind'])): ?>
                                            <small><?= e(RELATION_LABELS[$relative['kind']]) ?><?= $relative['end_year'] ? e(' · fin en ' . $relative['end_year']) : '' ?></small>
                                            <?php if ($user): ?>
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
                <?php if ($user): ?><button class="button button-light panel-action" type="button" data-new-relation><?= icon('plus', 17) ?> Ajouter une relation</button><?php endif ?>
            </section>

            <section class="panel content-panel" aria-labelledby="photos-heading">
                <h2 id="photos-heading"><?= icon('photo', 20) ?> Photos</h2>
                <?php if (!$photos): ?><p class="muted">Aucune photo.</p><?php endif ?>
                <?php if ($photos): ?>
                    <div class="photo-grid">
                        <?php foreach ($photos as $photo): ?>
                            <figure>
                                <a href="<?= e(url('media', ['id' => $photo['id']])) ?>">
                                    <img src="<?= e(url('media', ['id' => $photo['id']])) ?>" alt="<?= e($photo['title']) ?>" loading="lazy">
                                </a>
                                <figcaption><?= e($photo['title']) ?></figcaption>
                                <?php if ($user): view('remove-media', ['personId' => $person['id'], 'mediaId' => $photo['id']]); endif ?>
                            </figure>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
                <?php if ($user): view('upload', ['personId' => $person['id'], 'kind' => 'photo']); endif ?>
            </section>
        </div>
    </div>

    <?php view('graph', get_defined_vars()); ?>

    <?php if ($user): ?>
        <section class="profile-admin">
            <a class="text-link" href="<?= e(url('historique', ['id' => $person['id']])) ?>"><?= icon('activity', 18) ?> Historique de la fiche</a>
            <?php if ($user['role'] === 'admin'): ?>
                <details class="admin-tools">
                    <summary>Gestion de la fiche</summary>
                    <?php if ($duplicates): ?>
                        <form method="post" action="<?= e(url('personne', ['id' => $person['id']])) ?>" class="stacked-form"
                            data-confirm="Fusionner ces deux fiches ? Cette action supprime la fiche sélectionnée.">
                            <?= csrf_field() ?>
                            <label>Récupérer les données manquantes, les médias et les relations d’une autre fiche
                                <select name="duplicate_id" required>
                                    <option value="">Choisir un doublon</option>
                                    <?php foreach ($duplicates as $duplicate): ?>
                                        <option value="<?= e($duplicate['id']) ?>"><?= e(full_name($duplicate) . ($duplicate['birth_year'] ? ' · ' . $duplicate['birth_year'] : '')) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </label>
                            <button class="button button-light" name="action" value="merge-person">Fusionner les fiches</button>
                        </form>
                    <?php endif ?>
                    <form method="post" action="<?= e(url('personne', ['id' => $person['id']])) ?>"
                        data-confirm="Supprimer définitivement la fiche, ses relations et ses fichiers ?">
                        <?= csrf_field() ?>
                        <button class="button button-danger" name="action" value="delete-person">Supprimer la fiche</button>
                    </form>
                </details>
            <?php endif ?>
        </section>
    <?php endif ?>
</section>
