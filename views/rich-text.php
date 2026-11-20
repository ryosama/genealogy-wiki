<div data-rich-editor>
    <?php // site.js active la zone visuelle et synchronise le textarea soumis avec la fiche. ?>
    <div class="editor-toolbar" role="group" aria-label="Mise en forme" hidden>
        <button type="button" data-command="bold" title="Gras" aria-label="Gras"><strong>G</strong></button>
        <button type="button" data-command="italic" title="Italique" aria-label="Italique"><em>I</em></button>
        <button type="button" data-command="insertUnorderedList" title="Liste à puces" aria-label="Liste à puces">• Liste</button>
        <button type="button" data-command="insertOrderedList" title="Liste numérotée" aria-label="Liste numérotée">1. Liste</button>
        <button type="button" data-command="formatBlock" data-value="h3" title="Intertitre">Intertitre</button>
        <button type="button" data-command="formatBlock" data-value="p" title="Paragraphe">Paragraphe</button>
    </div>
    <?php // Le contrôleur passe le HTML par bio_html(), y compris après une erreur de saisie. ?>
    <div class="rich-input formatted" contenteditable="true" role="textbox" aria-multiline="true"
        aria-labelledby="<?= e($labelId) ?>" hidden><?= $html ?></div>
    <textarea name="<?= e($field) ?>" aria-labelledby="<?= e($labelId) ?>" rows="7"><?= e($html) ?></textarea>
</div>
