<?php // Le contrôleur réserve ces tâches aux administrateurs. ?>
<section class="container page-space edit-page todo-page">
    <div class="page-heading"><h1>À renseigner ultérieurement</h1></div>
    <?php if (!$tasks): ?>
        <p class="panel empty">Aucune tâche en attente.</p>
    <?php else: ?>
        <div class="todo-people">
            <?php // Les champs à compléter et l'éventuelle absence de lien sont regroupés par fiche.
            foreach ($tasks as $person): ?>
                <?php $profile = url('personne', ['id' => $person['id']]); ?>
                <section class="panel content-panel todo-person">
                    <h2><a href="<?= e($profile) ?>"><?= e($person['name']) ?></a></h2>
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
