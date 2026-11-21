<?php // Les administrateurs partagent un formulaire. Ses deux boutons sont dans l'en-tête et en bas de la fiche.
if ($canEdit): ?>
    <form id="profile-fields" class="profile-column" action="<?= e($profileUrl) ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save-person">
<?php endif ?>

<section class="panel content-panel" aria-labelledby="bio-heading">
    <h2 id="bio-heading"><?= icon('book', 20) ?> Biographie</h2>
    <?php if ($canEdit): ?>
        <div class="profile-field-editor">
            <div class="stacked-form form-grid">
                <?php foreach (['first_name' => 'Prénom', 'last_name' => 'Nom de famille'] as $key => $label): ?>
                    <?php $value = $values[$key] ?? ''; ?>
                    <label><?= e($label) ?>
                        <input id="person-<?= e($key) ?>" data-task-field name="<?= e($key) ?>"
                            value="<?= e($key === 'last_name' ? family_name($value) : $value) ?>" maxlength="100">
                    </label>
                <?php endforeach ?>
                <label>Sexe
                    <select id="person-sex" data-task-field name="sex">
                        <option value="">Non renseigné</option>
                        <?php foreach (SEX_LABELS as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($values['sex'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>
                <label>Lieu de vie
                    <input id="person-residence" data-task-field name="residence" value="<?= e($values['residence'] ?? '') ?>" maxlength="180">
                </label>
                <label>Date de naissance
                    <input id="person-birth_date" data-task-field name="birth_date" value="<?= e($values['birth_date'] ?? '') ?>"
                        placeholder="15/06/1880, 1880 ou vers 1880" maxlength="32">
                </label>
                <label>Lieu de naissance
                    <input id="person-birth_place" data-task-field name="birth_place" value="<?= e($values['birth_place'] ?? '') ?>" maxlength="180">
                </label>
                <div class="person-death">
                    <?php // Le champ masqué transmet 0 si la case est décochée, même sans JavaScript. ?>
                    <input type="hidden" name="deceased" value="0">
                    <label class="person-deceased-choice">
                        <input type="checkbox" name="deceased" value="1" <?= !empty($values['deceased']) ? 'checked' : '' ?>> Décédée
                    </label>
                    <div class="form-grid person-death-fields">
                        <label>Date de décès
                            <input id="person-death_date" data-task-field name="death_date" value="<?= e($values['death_date'] ?? '') ?>"
                                placeholder="15/06/1880, 1880 ou vers 1880" maxlength="32">
                        </label>
                        <label>Lieu de décès
                            <input id="person-death_place" data-task-field name="death_place" value="<?= e($values['death_place'] ?? '') ?>" maxlength="180">
                        </label>
                    </div>
                    <?php if (!empty($person['death_date']) || !empty($person['death_place'])): ?>
                        <p class="muted person-death-clear">Enregistrer la fiche comme vivante efface les informations de décès de la fiche.</p>
                    <?php endif ?>
                </div>
            </div>
            <?php view('rich-text', ['field' => 'biography', 'labelId' => 'bio-heading', 'html' => $values['biography'] ?? '']); ?>
        </div>
    <?php endif ?>
    <?php // Les visiteurs et l'impression utilisent les données enregistrées au format de lecture.
    if ($person): ?>
        <div<?= $canEdit ? ' class="profile-print-only"' : '' ?>>
            <dl class="life-dates">
                <?php if ($person['sex']): ?>
                    <div><dt>Sexe</dt><dd><?= e(SEX_LABELS[$person['sex']]) ?></dd></div>
                <?php endif ?>
                <?php $events = $person['deceased'] ? ['birth' => 'Naissance', 'death' => 'Décès'] : ['birth' => 'Naissance']; ?>
                <?php foreach ($events as $key => $label): ?>
                    <?php $date = $person[$key . '_date']; $place = $person[$key . '_place']; ?>
                    <div><dt><?= e($label) ?></dt><dd><?= e(implode(' · ', array_filter([$date, $place])) ?: 'Non renseigné') ?></dd></div>
                <?php endforeach ?>
                <?php if ($person['residence']): ?>
                    <div><dt>Lieu de vie</dt><dd><?= e($person['residence']) ?></dd></div>
                <?php endif ?>
            </dl>
            <?php // La biographie et les faits notables ont été filtrés par bio_html() avant leur enregistrement. ?>
            <?php if ($person['biography']): ?>
                <div class="formatted"><?= $person['biography'] ?></div>
            <?php endif ?>
        </div>
    <?php endif ?>
</section>

<section class="panel content-panel" aria-labelledby="facts-heading">
    <h2 id="facts-heading"><?= icon('activity', 21) ?> Faits notables</h2>
    <?php if ($canEdit): ?>
        <div class="profile-field-editor">
            <?php view('rich-text', ['field' => 'notable_facts', 'labelId' => 'facts-heading', 'html' => $values['notable_facts'] ?? '']); ?>
        </div>
    <?php endif ?>
    <?php if ($person): ?>
        <div<?= $canEdit ? ' class="profile-print-only"' : '' ?>>
            <?php if ($person['notable_facts']): ?>
                <div class="formatted"><?= $person['notable_facts'] ?></div>
            <?php else: ?>
                <p class="muted">Aucun fait notable renseigné.</p>
            <?php endif ?>
        </div>
    <?php endif ?>
</section>

<?php if ($canEdit): ?>
    </form>
<?php endif ?>
