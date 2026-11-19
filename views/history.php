<section class="container page-space edit-page">
    <?php if ($person): ?>
        <a class="text-link back-link" href="<?= e(url('personne', ['id' => $person['id']])) ?>"><?= icon('left', 17) ?> <?= e(full_name($person)) ?></a>
    <?php endif ?>
    <div class="page-heading"><h1>Historique des modifications</h1></div>
    <div class="activity-list history-list">
        <?php if (!$records): ?><p class="empty">Aucune modification à afficher.</p><?php endif ?>
        <?php foreach ($records as $record): ?>
            <article class="history-row">
                <div>
                    <strong><?= e(ACTION_LABELS[$record['action']] ?? $record['action']) ?></strong>
                    <p><?= e($record['person_name']) ?> · <?= e($record['editor_name']) ?></p>
                    <?php $details = $record['details'] ? json_decode($record['details'], true) : []; ?>
                    <?php if ($details && array_intersect_key($details, history_labels())): ?>
                        <details>
                            <summary><?= $record['action'] === 'edited' ? 'Valeurs précédentes' : 'Détails' ?></summary>
                            <dl>
                                <?php foreach (array_intersect_key($details, history_labels()) as $key => $value): ?>
                                    <?php $display = match (true) {
                                        !is_string($value) || trim($value) === '' => 'Non renseigné',
                                        $key === 'sex' => SEX_LABELS[$value] ?? 'Non renseigné',
                                        in_array($key, ['biography', 'notable_facts'], true) => html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8'),
                                        default => $value,
                                    }; ?>
                                    <div><dt><?= e(history_labels()[$key]) ?></dt><dd><?= e($display) ?></dd></div>
                                <?php endforeach ?>
                            </dl>
                        </details>
                    <?php endif ?>
                </div>
                <time datetime="<?= e(str_replace(' ', 'T', $record['created_at']) . 'Z') ?>"><?= e(display_instant($record['created_at'], true)) ?></time>
            </article>
        <?php endforeach ?>
    </div>
    <?php if ($page > 1 || $more): ?>
        <nav class="pagination" aria-label="Pages de l’historique">
            <?php $params = $id ? ['id' => $id] : []; ?>
            <?php if ($page > 1): ?><a class="button button-light" href="<?= e(url('historique', $params + ['page' => $page - 1])) ?>">Précédente</a><?php endif ?>
            <span>Page <?= e($page) ?></span>
            <?php if ($more): ?><a class="button button-light" href="<?= e(url('historique', $params + ['page' => $page + 1])) ?>">Suivante</a><?php endif ?>
        </nav>
    <?php endif ?>
</section>
