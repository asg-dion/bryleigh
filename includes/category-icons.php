<?php

function category_icon($name)
{
    $icons = [
        'bags' => '<path d="M5 8h14l1 13H4L5 8Z"></path><path d="M9 8V6a3 3 0 0 1 6 0v2"></path>',
        'bottoms' => '<path d="M6 3h12l1 18h-5l-2-8-2 8H5L6 3Z"></path><path d="M6 7h12"></path>',
        'dresses' => '<path d="M9 3h6l1 5 3 2-2 11H7L5 10l3-2 1-5Z"></path><path d="M8 8h8M7 13h10"></path>',
        'outerwear' => '<path d="m8 3-5 4 2 14h14l2-14-5-4-4 3-4-3Z"></path><path d="m8 3 5 5 5-5M13 8v13"></path>',
        'shoes' => '<path d="M3 15c3 0 5-2 6-5l4 3 3 1 3 1a3 3 0 0 1 2 3v2H3v-5Z"></path><path d="M3 18h18M10 14l2-2"></path>',
        'tops' => '<path d="m8 4-5 3 3 5 3-2v11h10V10l3 2 3-5-5-3-3 2h-6L8 4Z"></path>',
    ];
    $paths = $icons[strtolower($name)] ?? $icons['bags'];

    return '<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
}
