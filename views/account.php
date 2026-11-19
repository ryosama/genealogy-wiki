<section class="container page-space account-page">
    <div class="panel account-panel">
        <span class="stat-icon"><?= icon($mode === 'review' ? 'people' : 'user', 24) ?></span>
        <h1><?= e($title) ?></h1>

        <?php if ($mode === 'login'): ?>
            <form class="stacked-form" action="<?= e(url('connexion')) ?>" method="post">
                <?= csrf_field() ?>
                <label>Adresse e-mail
                    <input type="email" name="email" autocomplete="username" value="<?= e(input($_POST, 'email')) ?>" required>
                </label>
                <label>Mot de passe
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <button class="button button-primary" name="action" value="login">Se connecter</button>
            </form>
            <p class="account-alt"><a href="<?= e(url('inscription')) ?>">Demander un compte éditeur</a></p>
        <?php elseif ($mode === 'request'): ?>
            <p>Le mainteneur examine la demande. Une fois le compte validé, tu recevras un lien pour choisir ton mot de passe.</p>
            <form class="stacked-form" action="<?= e(url('inscription')) ?>" method="post">
                <?= csrf_field() ?>
                <div class="form-grid">
                    <label>Prénom
                        <input name="first_name" autocomplete="given-name" maxlength="100" value="<?= e(input($_POST, 'first_name')) ?>" required>
                    </label>
                    <label>Nom
                        <input name="last_name" autocomplete="family-name" maxlength="100" value="<?= e(input($_POST, 'last_name')) ?>" required>
                    </label>
                </div>
                <label>Adresse e-mail
                    <input type="email" name="email" autocomplete="email" maxlength="254" value="<?= e(input($_POST, 'email')) ?>" required>
                </label>
                <button class="button button-primary" name="action" value="request">Envoyer la demande</button>
            </form>
        <?php elseif ($mode === 'review'): ?>
            <p><strong><?= e(full_name($account)) ?></strong> (<?= e($account['email']) ?>) souhaite contribuer.</p>
            <form class="actions" action="<?= e(url('demande', ['token' => $token])) ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="review">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <button class="button button-primary" name="decision" value="approve">Valider</button>
                <button class="button button-danger" name="decision" value="reject">Refuser</button>
            </form>
        <?php else: ?>
            <p>Choisis un mot de passe pour le compte de <?= e(full_name($account)) ?>.</p>
            <form class="stacked-form" action="<?= e(url('activation', ['token' => $token])) ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <label>Mot de passe
                    <input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required>
                </label>
                <label>Confirmer le mot de passe
                    <input type="password" name="confirmation" autocomplete="new-password" minlength="12" maxlength="72" required>
                </label>
                <button class="button button-primary" name="action" value="activate">Activer le compte</button>
            </form>
        <?php endif ?>
    </div>
</section>
