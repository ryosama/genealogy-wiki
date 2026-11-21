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
    <?php if ($route === '' || $route === 'personnes'): ?><script src="<?= e(asset('search.js')) ?>" defer></script><?php endif ?>
    <?php // Le module de dessin n'est chargé que si la page fournit les données d'un arbre. ?>
    <?php if (isset($graph)): ?><script src="<?= e(asset('tree.js')) ?>" type="module"></script><?php endif ?>
    <?php if (in_array($route, ['photo', 'medaillon'], true)): ?><script src="<?= e(asset('photo-editor.js')) ?>" defer></script><?php endif ?>
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
            <?php // Les pages liées à une fiche font partie de la rubrique Personnes. ?>
            <?php foreach (['' => ['Accueil', 'home'], 'personnes' => ['Personnes', 'people'], 'arbre' => ['Arbre', 'tree']]
                as $section => [$label, $pictogram]):
                $active = $route === $section || ($section === 'personnes' && in_array($route, ['personne', 'historique', 'photo', 'medaillon'], true)); ?>
                <a href="<?= e(url($section)) ?>" <?= $active ? 'aria-current="page"' : '' ?>>
                    <?= icon($pictogram, 15) ?> <?= e($label) ?>
                </a>
            <?php endforeach ?>
        </nav>
        <?php // $user contient l'administrateur connecté. Pour un visiteur, sa valeur est nulle et le lien de connexion s'affiche.
        if ($user): ?>
            <div class="header-account">
                <a class="icon-button header-todo" href="<?= e(url('a-renseigner')) ?>" title="À renseigner ultérieurement"
                    aria-label="À renseigner ultérieurement" <?= $route === 'a-renseigner' ? 'aria-current="page"' : '' ?>><?= icon('check', 19) ?></a>
                <a class="icon-button header-accounts" href="<?= e(url('comptes')) ?>" title="Gestion des comptes"
                    aria-label="Gestion des comptes" <?= in_array($route, ['comptes', 'compte'], true) ? 'aria-current="page"' : '' ?>><?= icon('people', 19) ?></a>
                <a class="icon-button header-history" href="<?= e(url('historique')) ?>" title="Historique général" aria-label="Historique général"><?= icon('activity', 19) ?></a>
                <form method="post" action="<?= e(url()) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button class="button button-light header-session" name="action" value="logout" title="Se déconnecter" aria-label="Déconnexion">
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
            <?php // Proposer aux visiteurs de demander un compte administrateur depuis le pied de page.
            if (!$user): ?><a href="<?= e(url('inscription')) ?>">Demander un compte administrateur</a><?php endif ?>
        </nav>
    </div>
</footer>
</body>
</html>
