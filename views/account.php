<section class="container page-space account-page">
    <div class="panel account-panel">
        <span class="stat-icon"><?= icon('user', 24) ?></span>
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
            <p>Choisis ton mot de passe dès maintenant. Tu pourras te connecter après validation par un administrateur.</p>
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
                <?php view('password-fields', ['passwordRequired' => true]); ?>
                <input type="hidden" name="captcha_id" value="<?= e($challenge['id']) ?>">
                <label hidden>Site web <input name="website" tabindex="-1" autocomplete="off"></label>
                <label>Contrôle anti-spam · combien font <?= e($challenge['left']) ?> + <?= e($challenge['right']) ?> ?
                    <input name="captcha" inputmode="numeric" pattern="[0-9]{1,2}" minlength="1" maxlength="2" autocomplete="off" required>
                </label>
                <button class="button button-primary" name="action" value="request">Envoyer la demande</button>
            </form>
        <?php else: ?>
            <p>Choisis un mot de passe pour le compte de <?= e(full_name($account)) ?>.</p>
            <form class="stacked-form" action="<?= e(url('activation', ['token' => $token])) ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <?php view('password-fields', ['passwordRequired' => true]); ?>
                <button class="button button-primary" name="action" value="activate">Activer le compte</button>
            </form>
        <?php endif ?>
    </div>
</section>
