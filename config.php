<?php
/**
 * config.php — Configuration globale du portail (VERSION DÉMO STATIQUE)
 *
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │ Cette version ne se connecte à AUCUNE base de données.               │
 * │                                                                      │
 * │ L'ancienne version ouvrait une connexion mysqli vers la base         │
 * │ « bi_portal » de XAMPP (localhost / root / mot de passe vide).        │
 * │ Ce fonctionnement rendait le projet indéployable : hors de la machine│
 * │ de développement, la base n'existe pas et toutes les pages           │
 * │ tombaient sur « Erreur connexion ».                                   │
 * │                                                                      │
 * │ Ici, toutes les données (utilisateurs, indicateurs, modèles ML)      │
 * │ sont des tableaux PHP statiques. Le portail fonctionne donc sur      │
 * │ n'importe quel hébergement PHP >= 7.4, sans MySQL, sans Python,      │
 * │ sans licence Power BI.                                               │
 * └──────────────────────────────────────────────────────────────────────┘
 */

declare(strict_types=1);

// ── Identité de l'application ───────────────────────────────────────────────
const APP_NAME    = 'BI Portal';
const APP_BASELINE = 'Business Intelligence & Data Mining';
const APP_VERSION = '2.0.0-demo';

/**
 * MODE DÉMO
 *
 * true  → les pages affichent le bandeau d'avertissement et servent
 *         exclusivement les jeux de données statiques de /data.
 * false → à vous de rebrancher vos sources réelles (voir README.md).
 *
 * Laisser à true pour toute mise en ligne publique.
 */
const DEMO_MODE = true;

// ── Session ─────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    // Durcissement basique : le cookie de session n'est pas lisible en JS
    // et n'est pas transmis aux sites tiers.
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        // Le cookie passe en « secure » automatiquement si le site est en HTTPS.
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

// ── Chargement des jeux de données statiques ────────────────────────────────
require_once __DIR__ . '/data/users.php';

/**
 * Échappement HTML — raccourci utilisé dans toutes les vues.
 * Toute donnée injectée dans le HTML passe par cette fonction.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Formatage monétaire à la française : 1 234 567,89
 */
function money(float $value, int $decimals = 2): string
{
    return number_format($value, $decimals, ',', ' ');
}

/**
 * Formatage compact : 1,20 M / 420 K
 */
function compact_num(float $value): string
{
    if (abs($value) >= 1_000_000) {
        return number_format($value / 1_000_000, 2, ',', ' ') . ' M';
    }
    if (abs($value) >= 10_000) {
        return number_format($value / 1_000, 0, ',', ' ') . ' K';
    }
    // Entre 1 000 et 10 000, on garde une décimale : « 4 K » pour 3 960
    // faisait perdre la précision que l'indicateur est censé porter.
    if (abs($value) >= 1_000) {
        return rtrim(rtrim(number_format($value / 1_000, 2, ',', ' '), '0'), ',') . ' K';
    }
    return number_format($value, 2, ',', ' ');
}
