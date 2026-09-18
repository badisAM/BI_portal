<?php
/**
 * index.php — Point d'entrée du portail.
 *
 * Renvoie vers l'espace correspondant au rôle, ou vers la page de
 * connexion. Évite d'exposer la liste des fichiers si l'hébergeur
 * autorise le listage de répertoire.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

header('Location: ' . (is_logged_in()
    ? (ROLE_HOME[current_role()] ?? 'login.php')
    : 'login.php'));
exit;
