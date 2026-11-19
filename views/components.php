<?php
declare(strict_types=1);

function icon(string $name, int $size = 20): string
{
    $paths = [
        'tree' => '<rect x="9" y="2.5" width="6" height="5" rx="1"/><path d="M12 7.5v4.5m-7 4v-4h14v4"/><rect x="2" y="16" width="6" height="5" rx="1"/><rect x="16" y="16" width="6" height="5" rx="1"/>',
        'search' => '<circle cx="10.8" cy="10.8" r="6.3"/><path d="m15.4 15.4 4.4 4.4"/>',
        'people' => '<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2m2-15a3 3 0 0 1 0 6m4 9v-2a4 4 0 0 0-3-3.9"/>',
        'photo' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="9" r="1.5"/><path d="m3 17 4-4 3 3 4.5-5 6.5 6"/>',
        'activity' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'right' => '<path d="m9 6 6 6-6 6"/>',
        'left' => '<path d="m15 6-6 6 6 6"/>',
        'down' => '<path d="m6 9 6 6 6-6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2"/>',
        'print' => '<path d="M6 8V3h12v5M6 17H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6zm12-2h1"/>',
        'edit' => '<path d="m15 5 4 4M4 20l1-5L17 3a1.4 1.4 0 0 1 2 0l2 2a1.4 1.4 0 0 1 0 2L9 19z"/>',
        'book' => '<path d="M12 6a7 7 0 0 0-5-2H3v15h4a7 7 0 0 1 5 2 7 7 0 0 1 5-2h4V4h-4a7 7 0 0 0-5 2zm0 0v15"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8m-8 4h6"/>',
        'download' => '<path d="M12 3v12m-4-4 4 4 4-4M4 16v4h16v-4"/>',
        'link' => '<path d="M10 13a4 4 0 0 0 6 0l4-4a4 4 0 0 0-6-6l-2 2m2 6a4 4 0 0 0-6 0l-4 4a4 4 0 0 0 6 6l2-2"/>',
        'focus' => '<path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5"/><circle cx="12" cy="12" r="3"/>',
        'location' => '<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 0 1 14 0Z"/><circle cx="12" cy="10" r="2"/>',
        'logout' => '<path d="M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5m1-9h10m-4-4 4 4-4 4"/>',
    ];
    return '<svg width="' . $size . '" height="' . $size
        . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

function avatar(array $person, ?int $photoId = null, string $class = ''): string
{
    $label = e(initials($person));
    $variants = ['moss', 'clay', 'sand', 'slate'];
    $variant = $variants[$person['id'] % count($variants)];
    return '<span class="avatar avatar-' . $variant . ' ' . e($class) . '" aria-hidden="true">'
        . ($photoId ? '<img src="' . e(url('media', ['id' => $photoId])) . '" alt="" loading="lazy">' : $label) . '</span>';
}

function print_button(string $label): string
{
    return '<button class="button button-light" type="button" data-print>' . icon('print', 18) . e($label) . '</button>';
}
