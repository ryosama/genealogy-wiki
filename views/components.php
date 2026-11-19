<?php
declare(strict_types=1);

// Le sprite local fournit les pictogrammes. Les liens et boutons portent déjà leur libellé.
function icon(string $name, int $size = 20): string
{
    $symbol = asset('fontawesome.svg') . '#' . $name;
    return '<svg width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false">'
        . '<use href="' . e($symbol) . '" x="8%" y="8%" width="84%" height="84%"/></svg>';
}

function avatar(array $person, ?int $photoId = null, string $class = ''): string
{
    $label = e(initials($person));
    // Une couleur stable permet de reconnaître les avatars sans photo d'une page à l'autre.
    $variants = ['moss', 'clay', 'sand', 'slate'];
    $variant = $variants[$person['id'] % count($variants)];
    $image = !empty($person['portrait_id']) ? url('portrait', ['id' => $person['portrait_id']])
        : ($photoId ? url('media', ['id' => $photoId]) : null);
    return '<span class="avatar avatar-' . $variant . ' ' . e($class) . '" aria-hidden="true">'
        . ($image ? '<img src="' . e($image) . '" alt="" loading="lazy">' : $label) . '</span>';
}

function print_button(string $label): string
{
    return '<button class="button button-light" type="button" data-print>' . icon('print', 18) . e($label) . '</button>';
}
