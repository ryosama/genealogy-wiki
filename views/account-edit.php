<?php // Ce formulaire est réservé aux administrateurs. Le compte fondateur s'ouvre uniquement dans la session de son titulaire. ?>
<section class="container page-space account-page">
    <a class="text-link back-link" href="<?= e(url('comptes')) ?>"><?= icon('left', 17) ?> Gestion des comptes</a>
    <div class="panel account-panel">
        <h1><?= e($title) ?></h1>
        <?php // Lors d'une modification, rappeler l'état actuel du compte ciblé.
        if ($account): ?>
            <p><?= e(ACCOUNT_STATES[$account['state']]) ?></p>
        <?php endif ?>
        <form method="post" action="<?= e(url('compte', $id ? ['id' => $id] : [])) ?>" class="stacked-form">
            <?= csrf_field() ?>
            <div class="form-grid">
                <label>Prénom
                    <input name="first_name" value="<?= e($values['first_name']) ?>" maxlength="100" required>
                </label>
                <label>Nom
                    <input name="last_name" value="<?= e(family_name($values['last_name'])) ?>" maxlength="100" required>
                </label>
            </div>
            <label>Adresse e-mail
                <input type="email" name="email" value="<?= e($values['email']) ?>" maxlength="254" required>
            </label>
            <?php $passwordRequired = $account === null || !$account['has_password']; ?>
            <?php if (!$passwordRequired): ?>
                <p class="muted">Laisse les deux champs vides pour conserver le mot de passe actuel.</p>
            <?php endif ?>
            <?php view('password-fields', compact('passwordRequired')); ?>
            <div class="actions">
                <button class="button button-primary" name="action" value="save-account"><?= $id ? 'Enregistrer le compte' : 'Ajouter le compte' ?></button>
                <a class="button button-light" href="<?= e(url('comptes')) ?>">Annuler</a>
            </div>
        </form>
    </div>
</section>
