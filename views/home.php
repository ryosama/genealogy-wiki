<section class="hero">
    <div class="container hero-inner">
        <div class="hero-copy">
            <h1>Retrouvez<br>vos racines<span>.</span></h1>
            <p>Explorez l’arbre généalogique et découvrez les personnes, les photos et les documents qui racontent l’histoire de votre famille.</p>
        </div>
        <?php view('search', ['heroSearch' => true]); ?>
    </div>
</section>

<div class="container home-content">
    <section class="stats" aria-label="En chiffres">
        <?php foreach ([
            ['people', 'Personnes', 'people'],
            ['photos', 'Photos', 'photo'],
            ['families', 'Familles', 'tree'],
        ] as [$stat, $label, $icon]): ?>
            <div class="stat">
                <span class="stat-icon"><?= icon($icon, 25) ?></span>
                <div><strong><?= e(number_format((int) $stats[$stat], 0, ',', ' ')) ?></strong><span><?= e($label) ?></span></div>
            </div>
        <?php endforeach ?>
    </section>

    <div class="home-grid">
        <section aria-labelledby="activity-heading">
            <div class="section-top">
                <h2 id="activity-heading">Activité récente</h2>
                <a class="text-link" href="<?= e(url('personnes')) ?>">Toutes les personnes <?= icon('right', 17) ?></a>
            </div>
            <div class="activity-list">
                <?php if (!$recent): ?>
                    <p class="empty">Aucune fiche pour le moment.</p>
                <?php endif ?>
                <?php foreach ($recent as $item): ?>
                    <a class="activity-row" href="<?= e(url('personne', ['id' => $item['id']])) ?>">
                        <?= avatar($item, $item['photo_id']) ?>
                        <span class="activity-person">
                            <strong><?= e(full_name($item)) ?></strong>
                            <span><?= e(lifespan($item)) ?></span>
                        </span>
                        <span class="activity-meta">
                            <span><?= e(ACTION_LABELS[$item['action']] ?? 'Fiche mise à jour') ?> · <?= e($item['editor_name']) ?></span>
                            <time datetime="<?= e(str_replace(' ', 'T', $item['created_at']) . 'Z') ?>"><?= e(display_instant($item['created_at'])) ?></time>
                        </span>
                        <span class="activity-open"><?= icon('right', 17) ?></span>
                    </a>
                <?php endforeach ?>
            </div>
        </section>
        <aside aria-labelledby="explore-heading">
            <div class="section-top"><h2 id="explore-heading">Arbre généalogique</h2></div>
            <div class="explore-card">
                <div class="explore-illustration" aria-hidden="true">
                    <svg viewBox="0 0 308 150" fill="none">
                        <path d="M94 47h120M154 47v30m-80 20h160m-160 0v17m80-17v17m80-17v17" stroke="#829080" stroke-width="1.5"/>
                        <rect x="53" y="30" width="82" height="34" rx="7" fill="#f6f6ec" stroke="#b9c0ab"/>
                        <rect x="173" y="30" width="82" height="34" rx="7" fill="#f6f6ec" stroke="#b9c0ab"/>
                        <rect x="113" y="75" width="82" height="34" rx="7" fill="#d3ddc5" stroke="#829080"/>
                        <rect x="43" y="115" width="62" height="22" rx="5" fill="#f6f6ec" stroke="#b9c0ab"/>
                        <rect x="123" y="115" width="62" height="22" rx="5" fill="#f6f6ec" stroke="#b9c0ab"/>
                        <rect x="203" y="115" width="62" height="22" rx="5" fill="#f6f6ec" stroke="#b9c0ab"/>
                        <path d="M73 45h42m-31 7h20m88-7h42m-31 7h20m-90 38h42m-31 7h20M59 126h30m50 0h30m50 0h30" stroke="#849181" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="explore-content">
                    <p>Parcourez les générations et suivez les liens entre les membres de la famille.</p>
                    <a class="button button-primary" href="<?= e(url('arbre', $center ? ['id' => $center] : [])) ?>"><?= icon('tree', 18) ?> Explorer l’arbre</a>
                </div>
            </div>
        </aside>
    </div>
</div>
