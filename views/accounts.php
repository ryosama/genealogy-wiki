<?php // Le contrôleur réserve la gestion des comptes aux administrateurs. ?>
<section class="container page-space accounts-page">
    <div class="page-heading">
        <h1>Gestion des comptes</h1>
        <a class="button button-primary" href="<?= e(url('compte')) ?>"><?= icon('plus', 18) ?> Ajouter un administrateur</a>
    </div>

    <?php // Séparer les demandes et anciennes invitations à traiter des comptes déjà actifs.
    foreach (['waiting' => 'Demandes de compte', 'active' => 'Administrateurs actifs'] as $group => $heading): ?>
        <section class="panel accounts-section" aria-labelledby="accounts-<?= e($group) ?>">
            <h2 id="accounts-<?= e($group) ?>"><?= e($heading) ?></h2>
            <?php // Indiquer que la liste est vide ou détailler les comptes et les actions disponibles.
            if (!$accounts[$group]): ?>
                <p class="muted"><?= $group === 'waiting' ? 'Aucune demande à traiter.' : 'Aucun compte actif.' ?></p>
            <?php else: ?>
                <ul class="managed-accounts">
                    <?php // Afficher l'état de chaque compte et les actions accessibles à l'administrateur connecté.
                    foreach ($accounts[$group] as $account): ?>
                        <li class="managed-account">
                            <div class="managed-account-identity">
                                <strong><?= e(full_name($account)) ?></strong>
                                <span><?= e($account['email']) ?></span>
                                <span class="managed-account-state"><?= e(ACCOUNT_STATES[$account['state']]) ?></span>
                            </div>
                            <?php
                            // Les boutons du compte fondateur s'affichent seulement dans sa propre session.
                            $isOwnAccount = $account['id'] === $user['id'];
                            $isFirstAdmin = $account['id'] === $firstAdmin;
                            if ($isFirstAdmin): ?>
                                <span class="muted">Premier administrateur · <?= $isOwnAccount ? 'ton compte' : 'compte protégé' ?></span>
                            <?php endif ?>
                            <?php if (!$isFirstAdmin || $isOwnAccount): ?>
                                <div class="actions managed-account-actions">
                                    <a class="button button-light button-small" href="<?= e(url('compte', ['id' => $account['id']])) ?>">
                                        <?= $group === 'waiting' && !$account['has_password'] ? 'Définir un mot de passe' : 'Modifier' ?>
                                    </a>
                                    <?php // Les demandes peuvent être validées ou refusées. Les comptes actifs peuvent être supprimés.
                                    if ($group === 'waiting'): ?>
                                        <?php // La validation exige qu'un mot de passe ait déjà été choisi ou défini.
                                        if ($account['has_password']): ?>
                                            <form method="post" action="<?= e(url('comptes', ['id' => $account['id']])) ?>">
                                                <?= csrf_field() ?>
                                                <button class="button button-primary button-small" name="action" value="approve-account">Valider</button>
                                            </form>
                                        <?php endif ?>
                                        <form method="post" action="<?= e(url('comptes', ['id' => $account['id']])) ?>" data-confirm="Refuser et retirer cette demande de compte ?">
                                            <?= csrf_field() ?>
                                            <button class="button button-danger button-small" name="action" value="reject-account">Refuser</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="<?= e(url('comptes', ['id' => $account['id']])) ?>"
                                            data-confirm="<?= $isOwnAccount ? 'Supprimer définitivement ton compte ?' : 'Supprimer définitivement ce compte ?' ?>">
                                            <?= csrf_field() ?>
                                            <button class="button button-danger button-small" name="action" value="delete-account">Supprimer</button>
                                        </form>
                                    <?php endif ?>
                                </div>
                            <?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
    <?php endforeach ?>
</section>
