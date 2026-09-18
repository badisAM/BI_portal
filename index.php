<?php
// Point d'entrée unique : Vercel Hobby n'autorise que 12 fonctions.
$pages = [
    'login', 'logout', 'admin', 'dashboard_commercial', 'dashboard_employe',
    'dashboard_ml', 'config_employes', 'bi_ventes', 'bi_produits', 'bi_paiements',
];

$p = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$p = preg_replace('/\.php$/', '', $p);

if ($p === '' || !in_array($p, $pages, true)) {
    require __DIR__ . '/auth.php';
    $p = is_logged_in() ? rtrim(ROLE_HOME[current_role()] ?? 'login.php', '.php') : 'login';
}

require __DIR__ . '/' . $p . '.php';
