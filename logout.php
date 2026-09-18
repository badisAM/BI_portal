<?php
/**
 * logout.php — Déconnexion
 *
 * La version initiale appelait session_destroy() sans supprimer le cookie
 * de session côté navigateur : l'identifiant restait valide et pouvait
 * être rejoué. On vide donc les données, on expire le cookie, puis on
 * détruit la session.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]
    );
}

session_destroy();

header('Location: login.php');
exit;
