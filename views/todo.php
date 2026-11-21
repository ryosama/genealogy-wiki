<?php // Le contrôleur réserve ces tâches aux administrateurs. ?>
<section class="container page-space edit-page todo-page">
    <div class="page-heading"><h1>À renseigner ultérieurement</h1></div>
    <?php if (!$tasks): ?>
        <p class="panel empty">Aucune tâche en attente.</p>
    <?php else: ?>
        <div class="todo-people">
            <?php // Les champs à compléter et l'éventuelle absence de lien sont regroupés par fiche.
            foreach ($tasks as $person): ?>
                <?php
                $profile = url('personne', ['id' => $person['id']]);
                // Afficher les libellés saisis, qui peuvent contenir un jour précis ou une approximation.
                $dates = [];
                foreach (['birth_date' => 'Naissance', 'death_date' => 'Décès'] as $field => $label) {
                    if ($person[$field] !== '') {
                        $dates[] = $label . ' ' . $person[$field];
                    }
                }
                ?>
                <section class="panel content-panel todo-person<?= $person['sex'] ? ' todo-person-' . e($person['sex']) : '' ?>">
                    <header class="todo-person-heading">
                        <h2><a href="<?= e($profile) ?>"><?= sex_icon($person['sex']) ?><span><?= e($person['name']) ?></span></a></h2>
                        <?php if ($dates): ?>
                            <p class="todo-person-dates">
                                <?php foreach ($dates as $date): ?><span><?= e($date) ?></span><?php endforeach ?>
                            </p>
                        <?php endif ?>
                    </header>
                    <ul class="todo-fields">
                        <?php foreach ($person['fields'] as $field): ?>
                            <li>
                                <a class="text-link" href="<?= e($profile . '#person-' . $field) ?>">
                                    <?= icon($field === 'relations' ? 'link' : 'edit', 16) ?> <?= e(PERSON_TASK_LABELS[$field]) ?>
                                </a>
                                <form class="inline-form" method="post" action="<?= e(url('a-renseigner', ['id' => $person['id']])) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="field" value="<?= e($field) ?>">
                                    <button class="button button-light button-small" name="action" value="ignore-task"
                                        aria-label="<?= e('Ignorer ' . PERSON_TASK_LABELS[$field] . ' pour ' . $person['name']) ?>">
                                        <?= icon('circle-xmark', 15) ?> Ignorer
                                    </button>
                                </form>
                            </li>
                        <?php endforeach ?>
                    </ul>
                </section>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</section>
