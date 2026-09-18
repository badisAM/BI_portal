<?php
/**
 * auth.php — Authentification et contrôle d'accès (sans base de données)
 *
 * Toute la logique qui vivait auparavant dans des requêtes SQL est ici,
 * en PHP pur, appliquée au tableau USERS de data/users.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Renvoie la liste des utilisateurs, en appliquant les activations /
 * désactivations faites pendant la session.
 *
 * Pourquoi la session ? Parce qu'un hébergement peut être en lecture seule
 * et qu'une démo ne doit rien écrire sur le disque. Les changements d'état
 * sont donc réels et visibles pendant la visite, puis oubliés à la
 * déconnexion — ce qui remet la démo à zéro pour le visiteur suivant.
 */
function all_users(): array
{
    $overrides = $_SESSION['user_overrides'] ?? [];

    return array_map(static function (array $u) use ($overrides): array {
        if (isset($overrides[$u['id']])) {
            $u['actif'] = $overrides[$u['id']];
        }
        return $u;
    }, USERS);
}

/** Retrouve un utilisateur par son email (insensible à la casse). */
function find_user_by_email(string $email): ?array
{
    $email = strtolower(trim($email));
    foreach (all_users() as $u) {
        if (strtolower($u['email']) === $email) {
            return $u;
        }
    }
    return null;
}

/** Retrouve un utilisateur par son identifiant. */
function find_user_by_id(int $id): ?array
{
    foreach (all_users() as $u) {
        if ($u['id'] === $id) {
            return $u;
        }
    }
    return null;
}

/**
 * Tente une connexion.
 *
 * Équivalent statique de l'ancienne requête :
 *   SELECT * FROM users WHERE email=? AND password=? AND actif=1
 *
 * @return array{0: bool, 1: string} [succès, message d'erreur]
 */
function attempt_login(string $email, string $password): array
{
    $user = find_user_by_email($email);

    // Message volontairement identique dans les deux cas : révéler qu'un
    // email existe mais que le mot de passe est faux aide un attaquant à
    // dresser la liste des comptes valides.
    $generic = 'Email ou mot de passe incorrect.';

    if ($user === null) {
        // On calcule quand même un hash pour que le temps de réponse ne
        // trahisse pas l'existence du compte (attaque temporelle).
        password_verify($password, '$2y$12$' . str_repeat('x', 53));
        return [false, $generic];
    }

    if (!password_verify($password, $user['password'])) {
        return [false, $generic];
    }

    if ((int) $user['actif'] !== 1) {
        return [false, 'Ce compte est désactivé. Contactez un administrateur.'];
    }

    // Régénérer l'identifiant de session à la connexion empêche la
    // fixation de session.
    session_regenerate_id(true);

    $_SESSION['id']    = $user['id'];
    $_SESSION['nom']   = $user['nom'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role']  = $user['role'];
    $_SESSION['poste'] = $user['poste'];

    return [true, ''];
}

/** L'utilisateur est-il connecté ? */
function is_logged_in(): bool
{
    return isset($_SESSION['role'], $_SESSION['id']);
}

/** Rôle courant, ou chaîne vide. */
function current_role(): string
{
    return (string) ($_SESSION['role'] ?? '');
}

/** Nom affiché de l'utilisateur courant. */
function current_name(): string
{
    return (string) ($_SESSION['nom'] ?? 'Invité');
}

/**
 * Barrière d'accès à placer en tête de chaque page protégée.
 *
 * @param string|string[] $roles Rôle(s) autorisé(s). Vide = tout connecté.
 */
function require_role(array|string $roles = []): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }

    $roles = (array) $roles;
    if ($roles !== [] && !in_array(current_role(), $roles, true)) {
        // Connecté mais pas au bon niveau : on le renvoie vers SA page
        // d'accueil plutôt que vers le login, qui serait déroutant.
        $home = ROLE_HOME[current_role()] ?? 'login.php';
        header('Location: ' . $home . '?refus=1');
        exit;
    }
}

/** Active / désactive un compte (démo : effet limité à la session). */
function toggle_user(int $id): void
{
    $user = find_user_by_id($id);
    if ($user === null || $user['role'] === 'admin') {
        return; // on ne désactive jamais un administrateur
    }
    $_SESSION['user_overrides'][$id] = (int) $user['actif'] === 1 ? 0 : 1;
}

/** Jeton anti-CSRF pour les formulaires et les actions sensibles. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Vérifie un jeton anti-CSRF. */
function csrf_valid(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}
