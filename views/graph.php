<section class="panel graph-panel" data-graph data-center="<?= e($center) ?>" data-route="<?= e($route) ?>"
    data-editable="<?= $user ? 'true' : 'false' ?>" aria-label="Arbre généalogique interactif">
    <div class="graph-heading">
        <div>
            <h2><?= icon('tree', 22) ?> <?= $route === 'personne' ? 'Arbre généalogique' : 'Vue familiale' ?></h2>
            <p>Choisissez une personne pour ouvrir sa fiche.</p>
        </div>
        <div class="actions graph-actions">
            <button class="button button-light" type="button" data-pick-center><?= icon('search', 17) ?> Changer le centre</button>
            <?php if ($user): ?>
                <button class="button button-light" type="button" data-new-relation><?= icon('link', 17) ?> Ajouter un lien</button>
            <?php endif ?>
            <details class="print-menu">
                <summary class="button button-light"><?= icon('print', 18) ?> Imprimer l’arbre <?= icon('down', 14) ?></summary>
                <div class="menu-dropdown">
                    <button type="button" data-print-tree="view">Vue affichée</button>
                    <button type="button" data-print-tree="ancestors">Branche ascendante complète</button>
                    <button type="button" data-print-tree="descendants">Branche descendante complète</button>
                    <button type="button" data-print-tree="both">Ascendants et descendants complets</button>
                </div>
            </details>
        </div>
    </div>
    <div class="graph-tools">
        <div class="generations">
            <button class="button button-light button-small" type="button" data-expand="ancestors"><?= icon('plus', 16) ?> Ascendants</button>
            <button class="button button-light button-small" type="button" data-expand="descendants"><?= icon('plus', 16) ?> Descendants</button>
        </div>
        <div class="zoom-actions" role="group" aria-label="Zoom de l’arbre">
            <button class="icon-button" type="button" data-zoom="out" title="Zoom arrière" aria-label="Zoom arrière"><?= icon('minus', 17) ?></button>
            <output class="zoom-level" data-zoom-level>100 %</output>
            <button class="icon-button" type="button" data-zoom="in" title="Zoom avant" aria-label="Zoom avant"><?= icon('plus', 17) ?></button>
            <button class="icon-button" type="button" data-zoom="reset" title="Réinitialiser la vue" aria-label="Réinitialiser la vue"><?= icon('focus', 17) ?></button>
        </div>
    </div>
    <div class="graph-viewport" tabindex="0" aria-label="Graphique des générations défilant">
        <div class="graph-surface"></div>
    </div>
    <div class="print-graph" aria-hidden="true"></div>
    <div class="graph-legend">
        <span><i class="legend-centre"></i> Personne centrale</span>
        <span><i class="legend-line"></i> Filiation biologique</span>
        <span><i class="legend-line legend-adoption"></i> Filiation adoptive</span>
        <span><i class="legend-line legend-union"></i> Mariage ou union</span>
        <span><i class="legend-line legend-sibling"></i> Fratrie déclarée</span>
    </div>
    <?php if ($user): ?>
        <p class="graph-help">Clic droit ou appui prolongé sur une fiche ou un lien pour afficher ses actions. Glissez une case pour la déplacer temporairement.</p>
        <div class="graph-pair" data-pair-status hidden>
            <p role="status"></p>
            <button class="button button-light button-small" type="button" data-cancel-pair>Annuler</button>
        </div>
    <?php else: ?>
        <p class="graph-help">Glissez une case pour la déplacer temporairement.</p>
    <?php endif ?>
    <noscript><p class="empty">Active JavaScript pour afficher le graphique. Les relations restent accessibles dans les fiches.</p></noscript>
    <?php // Le JSON permet de réafficher les formulaires de l'arbre après une erreur serveur. ?>
    <script type="application/json" data-graph-data><?= json_data($graph + ['attempted' => $attemptedRelation, 'attemptedRelative' => $attemptedRelative]) ?></script>
    <?php view('graph-menu', compact('user', 'center', 'route')); ?>

    <dialog data-centre-dialog>
        <form method="dialog" class="dialog-close"><button class="icon-button" aria-label="Fermer"><?= icon('close') ?></button></form>
        <h2>Choisir la personne centrale</h2>
        <label>Nom ou prénom <input type="search" data-center-search placeholder="Rechercher une personne" autocomplete="off"></label>
        <ul class="center-results" data-center-results></ul>
    </dialog>

    <?php if ($user): ?>
        <dialog data-link-chooser>
            <form method="dialog" class="dialog-close"><button class="icon-button" aria-label="Fermer"><?= icon('close') ?></button></form>
            <h2>Choisir la relation</h2>
            <p>Ce trait est commun à plusieurs filiations.</p>
            <ul class="center-results" data-link-results></ul>
        </dialog>
        <dialog data-relation-dialog>
            <form method="dialog" class="dialog-close"><button class="icon-button" aria-label="Fermer"><?= icon('close') ?></button></form>
            <h2 data-relation-title>Ajouter une relation</h2>
            <p data-relation-error class="notice notice-error" role="alert" hidden></p>
            <button class="button button-light pair-button" type="button" data-pick-pair><?= icon('link', 17) ?> Choisir deux cases sur l’arbre</button>
            <form action="<?= e(url($route, ['id' => $center])) ?>" method="post" class="stacked-form" data-relation-form>
                <?= csrf_field() ?>
                <input type="hidden" name="relation_id">
                <label>Type de relation
                    <select name="kind">
                        <?php foreach (RELATION_LABELS as $key => $label): ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>
                <label><span data-a-label>Parent</span>
                    <select name="a_id" required></select>
                </label>
                <label><span data-b-label>Enfant</span>
                    <select name="b_id" required></select>
                </label>
                <label data-end-label hidden>Année de fin (facultatif)
                    <input name="end_year" pattern="[0-9]{1,4}" inputmode="numeric" maxlength="4">
                </label>
                <div class="actions">
                    <button class="button button-primary" name="action" value="save-relation">Enregistrer le lien</button>
                    <button class="button button-danger" name="action" value="delete-relation" type="submit" formnovalidate data-remove-link hidden>Supprimer</button>
                </div>
            </form>
        </dialog>
    <?php endif ?>
</section>
