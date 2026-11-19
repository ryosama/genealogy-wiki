<section class="container page-space">
    <div class="page-heading">
        <h1>Personnes</h1>
        <?php if ($user): ?>
            <a class="button button-primary" href="<?= e(url('edition')) ?>"><?= icon('plus', 18) ?> Nouvelle personne</a>
        <?php endif ?>
    </div>
    <div class="panel search-panel"><?php view('search', ['filters' => $filters]); ?></div>
    <div class="section-top directory-count">
        <p><?= e($total) ?> <?= $total === 1 ? 'personne trouvée' : 'personnes trouvées' ?></p>
        <?php if (array_filter($filters)): ?><a class="text-link" href="<?= e(url('personnes')) ?>">Effacer les filtres</a><?php endif ?>
    </div>
    <?php if ($people): ?>
        <div class="directory-grid">
            <?php foreach ($people as $item): ?>
                <a class="person-card" href="<?= e(url('personne', ['id' => $item['id']])) ?>">
                    <?= avatar($item, $item['photo_id']) ?>
                    <span><strong><?= e(full_name($item)) ?></strong><small><?= e(lifespan($item)) ?></small></span>
                    <?= icon('right', 18) ?>
                </a>
            <?php endforeach ?>
        </div>
    <?php else: ?>
        <p class="panel empty">Aucune fiche ne correspond à cette recherche.</p>
    <?php endif ?>
    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Pages des résultats">
            <?php if ($page > 1): ?><a class="button button-light" href="<?= e(url('personnes', $filters + ['page' => $page - 1])) ?>">Précédente</a><?php endif ?>
            <span>Page <?= e($page) ?> sur <?= e($pages) ?></span>
            <?php if ($page < $pages): ?><a class="button button-light" href="<?= e(url('personnes', $filters + ['page' => $page + 1])) ?>">Suivante</a><?php endif ?>
        </nav>
    <?php endif ?>
</section>
