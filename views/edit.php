<section class="container page-space edit-page">
    <a class="text-link back-link" href="<?= e($id ? url('personne', ['id' => $id]) : url('personnes')) ?>">
        <?= icon('left', 17) ?> <?= $id ? 'Revenir à la fiche' : 'Toutes les personnes' ?>
    </a>
    <div class="page-heading"><h1><?= e($title) ?></h1></div>
    <form class="stacked-form" action="<?= e(url('edition', $id ? ['id' => $id] : [])) ?>" method="post">
        <?= csrf_field() ?>
        <section class="panel form-panel">
            <h2>Informations personnelles</h2>
            <div class="form-grid">
                <?php foreach (['first_name' => 'Prénom', 'last_name' => 'Nom'] as $key => $label): ?>
                    <label><?= e($label) ?>
                        <input name="<?= e($key) ?>" value="<?= e($person[$key] ?? '') ?>" maxlength="100" required>
                    </label>
                <?php endforeach ?>
                <?php foreach (['birth' => 'Naissance', 'death' => 'Décès'] as $key => $label): ?>
                    <label><?= e($label) ?>
                        <input name="<?= e($key) ?>_date" value="<?= e($person[$key . '_date'] ?? '') ?>" placeholder="15/06/1880, 1880 ou vers 1880" maxlength="32">
                    </label>
                    <label><?= $key === 'birth' ? 'Lieu de naissance' : 'Lieu de décès' ?>
                        <input name="<?= e($key) ?>_place" value="<?= e($person[$key . '_place'] ?? '') ?>" maxlength="180">
                    </label>
                <?php endforeach ?>
                <label>Lieu de vie
                    <input name="residence" value="<?= e($person['residence'] ?? '') ?>" maxlength="180">
                </label>
            </div>
        </section>
        <?php foreach (['biography' => 'Biographie', 'notable_facts' => 'Faits notables'] as $key => $label): ?>
            <section class="panel form-panel" aria-labelledby="<?= e($key) ?>-label">
                <h2 id="<?= e($key) ?>-label"><?= e($label) ?></h2>
                <div data-rich-editor>
                    <div class="editor-toolbar" role="group" aria-label="Mise en forme" hidden>
                        <button type="button" data-command="bold" title="Gras" aria-label="Gras"><strong>G</strong></button>
                        <button type="button" data-command="italic" title="Italique" aria-label="Italique"><em>I</em></button>
                        <button type="button" data-command="insertUnorderedList" title="Liste à puces" aria-label="Liste à puces">• Liste</button>
                        <button type="button" data-command="insertOrderedList" title="Liste numérotée" aria-label="Liste numérotée">1. Liste</button>
                        <button type="button" data-command="formatBlock" data-value="h3" title="Intertitre">Intertitre</button>
                        <button type="button" data-command="formatBlock" data-value="p" title="Paragraphe">Paragraphe</button>
                    </div>
                    <div class="rich-input formatted" contenteditable="true" role="textbox" aria-multiline="true"
                        aria-labelledby="<?= e($key) ?>-label" hidden><?= $person[$key] ?? '' ?></div>
                    <textarea name="<?= e($key) ?>" aria-labelledby="<?= e($key) ?>-label" rows="7"><?= e($person[$key] ?? '') ?></textarea>
                </div>
            </section>
        <?php endforeach ?>
        <div class="actions">
            <button class="button button-primary" name="action" value="save-person">Enregistrer la fiche</button>
            <a class="button button-light" href="<?= e($id ? url('personne', ['id' => $id]) : url('personnes')) ?>">Annuler</a>
        </div>
    </form>
</section>
