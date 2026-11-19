<?php require_once PROJECT_ROOT . '/views/components.php'; ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= e($title) ?> · Racines</title>
    <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
    <script src="<?= e(asset('site.js')) ?>" defer></script>
    <?php if (isset($graph)): ?><script src="<?= e(asset('tree.js')) ?>" type="module"></script><?php endif ?>
</head>
<body class="<?= $route === 'arbre' ? 'tree-page' : '' ?>">
<a class="skip-link" href="#main">Aller au contenu</a>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url()) ?>" aria-label="Racines, accueil">
            <span class="brand-icon"><?= icon('tree', 22) ?></span>
            <span>Racines</span>
        </a>
        <nav class="primary-nav" aria-label="Navigation principale">
            <?php foreach (['' => 'Accueil', 'personnes' => 'Personnes', 'arbre' => 'Arbre'] as $section => $label):
                $active = $route === $section || ($section === 'personnes' && in_array($route, ['personne', 'edition', 'historique'], true)); ?>
                <a href="<?= e(url($section)) ?>" <?= $active ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach ?>
        </nav>
        <?php if ($user): ?>
            <div class="header-account">
                <?php if ($user['role'] === 'admin'): ?>
                    <a class="icon-button header-history" href="<?= e(url('historique')) ?>" title="Historique général" aria-label="Historique général"><?= icon('activity', 19) ?></a>
                <?php endif ?>
                <form method="post" action="<?= e(url()) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button class="button button-light header-session" name="action" value="logout" title="Se déconnecter">
                        <?= icon('logout', 18) ?><span>Déconnexion</span>
                    </button>
                </form>
            </div>
        <?php else: ?>
            <a class="button button-light header-session" href="<?= e(url('connexion')) ?>">
                <?= icon('user', 18) ?><span>Connexion</span>
            </a>
        <?php endif ?>
    </div>
</header>
<main id="main">
    <?php if ($notice || $error): ?>
        <div class="container notices" aria-live="polite">
            <?php if ($notice): ?><p class="notice"><?= e($notice) ?></p><?php endif ?>
            <?php if ($error): ?><p class="notice notice-error" role="alert"><?= e($error) ?></p><?php endif ?>
        </div>
    <?php endif ?>
    <?php view($template, get_defined_vars()); ?>
</main>
<footer class="site-footer">
    <div class="container footer-inner">
        <a class="brand brand-footer" href="<?= e(url()) ?>"><span class="brand-icon"><?= icon('tree', 18) ?></span> Racines</a>
        <nav aria-label="Liens de pied de page">
            <a href="<?= e(url('personnes')) ?>">Personnes</a>
            <a href="<?= e(url('arbre')) ?>">Arbre</a>
            <?php if (!$user): ?><a href="<?= e(url('inscription')) ?>">Demander un compte</a><?php endif ?>
        </nav>
    </div>
</footer>
</body>
</html>
