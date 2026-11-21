<div class="graph-context-menu" data-context-menu role="menu" aria-label="Actions dans l’arbre" hidden>
    <p class="graph-menu-title" data-context-title aria-hidden="true"></p>
    <div data-person-menu role="group" aria-label="Actions sur la personne">
        <a role="menuitem" tabindex="-1" href="<?= e(url('personne')) ?>" data-person-link><?= icon('user', 16) ?> Ouvrir la fiche</a>
        <?php // La fiche s'ouvre pour tous. Un administrateur peut aussi créer des proches depuis le menu.
        if ($user): ?>
            <?php // Un raccourci par type de couple transmet le choix d'une union ou d'un mariage au formulaire.
            foreach (RELATIVE_PRESETS['partners'] as $key => $preset): ?>
                <button type="button" role="menuitem" tabindex="-1" data-menu-action="create-relative"
                    data-preset="<?= e($key) ?>" data-relative-group="partners">
                    <?= icon('link', 16) ?> <?= e($preset['title']) ?>
                </button>
            <?php endforeach ?>
            <?php // Regrouper les autres raccourcis par place du proche dans la famille.
            foreach (['parents' => 'Ascendants', 'children' => 'Descendants', 'siblings' => 'Frères et sœurs'] as $group => $label): ?>
                <div class="graph-menu-group" role="group" aria-label="<?= e($label) ?>">
                    <p class="graph-menu-label" aria-hidden="true"><?= e($label) ?></p>
                    <?php // Chaque option indique au formulaire le type de proche à créer et affiche l'icône correspondante.
                    foreach (RELATIVE_PRESETS[$group] as $key => $preset): ?>
                        <button type="button" role="menuitem" tabindex="-1" data-menu-action="create-relative"
                            data-preset="<?= e($key) ?>" data-relative-group="<?= e($group) ?>">
                            <?= icon($preset['sex'], 16) ?> <?= e($preset['title']) ?>
                        </button>
                    <?php endforeach ?>
                </div>
            <?php endforeach ?>
            <?php // La commande ouvre la confirmation de suppression de la fiche sélectionnée. ?>
            <button class="graph-menu-danger" type="button" role="menuitem" tabindex="-1" data-menu-action="delete-person">
                <?= icon('trash', 16) ?> Supprimer la fiche
            </button>
        <?php endif ?>
    </div>
    <?php // Les administrateurs peuvent modifier ou retirer la relation sélectionnée.
    if ($user): ?>
        <div data-edge-menu role="group" aria-label="Actions sur le lien">
            <button type="button" role="menuitem" tabindex="-1" data-menu-action="edit-link">
                <?= icon('edit', 16) ?> Modifier le type de lien
            </button>
            <button class="graph-menu-danger" type="button" role="menuitem" tabindex="-1" data-menu-action="delete-link">
                <?= icon('trash', 16) ?> Supprimer le lien
            </button>
        </div>
    <?php endif ?>
</div>

<?php // Le dialogue réutilise le raccourci et la fiche sélectionnés pour créer la nouvelle personne et ses liens.
if ($user): ?>
    <dialog data-relative-dialog aria-labelledby="relative-title">
        <form method="dialog" class="dialog-close"><button class="icon-button" aria-label="Fermer"><?= icon('close') ?></button></form>
        <h2 id="relative-title" data-relative-title>Créer un proche</h2>
        <p class="muted" data-relative-reference></p>
        <p class="notice notice-error" data-relative-error role="alert" hidden></p>
        <form action="<?= e(url($route, ['id' => $center])) ?>" method="post" class="stacked-form">
            <?= csrf_field() ?>
            <input type="hidden" name="reference_id">
            <input type="hidden" name="preset">
            <label>Prénom <input name="first_name" required maxlength="100"></label>
            <label>Nom <input name="last_name" required maxlength="100"></label>
            <label data-relative-sex hidden>Sexe
                <select name="sex" disabled>
                    <option value="">Non renseigné</option>
                    <?php // Le menu déverrouille ce champ lors de la création d'un conjoint pour permettre de changer son sexe proposé.
                    foreach (SEX_LABELS as $value => $label): ?>
                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <button class="button button-primary" name="action" value="create-relative"><?= icon('link', 17) ?> Créer la fiche et le lien</button>
        </form>
    </dialog>
    <?php // Le menu remplit l'identifiant de la fiche ciblée et soumet ce formulaire après confirmation. ?>
    <form action="<?= e(url($route, ['id' => $center])) ?>" method="post" data-graph-delete-person hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete-person">
        <input type="hidden" name="target_id">
    </form>
<?php endif ?>
