<section class="container page-space">
    <div class="page-heading">
        <div>
            <h1>Arbre généalogique</h1>
            <?php if ($person): ?>
                <p>Autour de <a class="text-link" href="<?= e(url('personne', ['id' => $person['id']])) ?>"><?= e(full_name($person)) ?></a></p>
            <?php endif ?>
        </div>
    </div>
    <?php if ($person): ?>
        <?php view('graph', get_defined_vars()); ?>
    <?php else: ?>
        <div class="panel empty">
            <p>Aucune personne à afficher.</p>
            <?php // Même lorsque l'arbre est vide, un administrateur peut créer la première fiche.
            if ($user): ?><a class="button button-primary" href="<?= e(url('personne')) ?>"><?= icon('plus', 18) ?> Ajouter une personne</a><?php endif ?>
        </div>
    <?php endif ?>
</section>
