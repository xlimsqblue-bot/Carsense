<?php
declare(strict_types=1);
defined('PARKSENSE') || exit('Forbidden');

/** Escape for HTML output. Use on every value printed from users or the DB. */
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redirect to a fixed path inside the app. Never pass user input here. */
function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}
