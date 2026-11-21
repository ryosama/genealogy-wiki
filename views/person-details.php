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
                <?php foreach (['first_name' => 'Prénom', 'last_name' => 'Nom'] as $key => $label): ?>
                    <?php $value = $values[$key] ?? ''; ?>
                    <label><?= e($label) ?>
                        <input name="<?= e($key) ?>" value="<?= e($key === 'last_name' ? family_name($value) : $value) ?>" maxlength="100" required>
                    </label>
                <?php endforeach ?>
                <label>Sexe
                    <select name="sex">
                        <option value="">Non renseigné</option>
                        <?php foreach (SEX_LABELS as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($values['sex'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>
                <label>Lieu de vie
                    <input name="residence" value="<?= e($values['residence'] ?? '') ?>" maxlength="180">
                </label>
                <?php foreach (['birth' => 'de naissance', 'death' => 'de décès'] as $key => $label): ?>
                    <label>Date <?= e($label) ?>
                        <input name="<?= e($key) ?>_date" value="<?= e($values[$key . '_date'] ?? '') ?>" placeholder="15/06/1880, 1880 ou vers 1880" maxlength="32">
                    </label>
                    <label>Lieu <?= e($label) ?>
                        <input name="<?= e($key) ?>_place" value="<?= e($values[$key . '_place'] ?? '') ?>" maxlength="180">
                    </label>
                <?php endforeach ?>
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
                <?php foreach (['birth' => 'Naissance', 'death' => 'Décès'] as $key => $label): ?>
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
