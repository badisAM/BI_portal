<?php
/**
 * data/users.php — Annuaire utilisateurs STATIQUE
 *
 * Remplace la table `users` de MySQL. Aucune base n'est requise :
 * le portail fonctionne à l'identique sur n'importe quel hébergement PHP.
 *
 * ── Ce qui a changé par rapport à la version initiale ──────────────────────
 *
 *  1. Les mots de passe ne sont plus en MD5.
 *     MD5 est cassé : une base de mots de passe MD5 se déchiffre en ligne
 *     en quelques secondes. On utilise password_hash()/password_verify()
 *     (bcrypt), qui est le standard PHP depuis la version 5.5.
 *
 *  2. Le login ne construit plus de requête SQL par concaténation.
 *     L'ancienne requête  WHERE email='$email'  était vulnérable à
 *     l'injection SQL : saisir  ' OR '1'='1  dans le champ email
 *     suffisait à se connecter sans mot de passe.
 *     Ici la recherche se fait en PHP sur un tableau : la faille disparaît.
 *
 * ── Ajouter / modifier un compte ───────────────────────────────────────────
 *
 *  Générez le hash avec :
 *      php -r "echo password_hash('votre_mot_de_passe', PASSWORD_DEFAULT);"
 *  puis collez-le dans le champ 'password' d'une nouvelle entrée.
 *
 *  Rôles disponibles : admin | commercial | employe
 */

declare(strict_types=1);

/**
 * Comptes de démonstration.
 *
 * Mots de passe en clair (affichés sur la page de connexion, c'est une démo) :
 *   admin@bi.local       → admin123
 *   commercial@bi.local  → commercial123
 *   employe@bi.local     → employe123
 *
 * ⚠ En usage réel : changez ces trois hashes et retirez l'encart
 *   « comptes de démonstration » de login.php.
 */
const USERS = [
    [
        'id'       => 1,
        'nom'      => 'Badis Ammar',
        'email'    => 'admin@bi.local',
        'password' => '$2y$12$/gTH8rc3mheiJYRP2CmirOw6Z6kdxB15bN5rs15RHdnTDUeNmZe8e',
        'role'     => 'admin',
        'actif'    => 1,
        'poste'    => 'Administrateur BI',
    ],
    [
        'id'       => 2,
        'nom'      => 'Sonia Gharbi',
        'email'    => 'commercial@bi.local',
        'password' => '$2y$12$y7xMSBhX3QFoXnibYwLvnOgMB9cFrt9LmOOFZVcdyCmHT03kotkn.',
        'role'     => 'commercial',
        'actif'    => 1,
        'poste'    => 'Responsable commercial',
    ],
    [
        'id'       => 3,
        'nom'      => 'Karim Belhadj',
        'email'    => 'employe@bi.local',
        'password' => '$2y$12$fC66jiVdJJN/KDDU/I.Ete0Z4kMWcP5Fg4mug2MOnuTAmpUS1I2Qy',
        'role'     => 'employe',
        'actif'    => 1,
        'poste'    => 'Chef de rayon',
    ],
    [
        'id'       => 4,
        'nom'      => 'Ines Trabelsi',
        'email'    => 'ines.trabelsi@bi.local',
        'password' => '$2y$12$y7xMSBhX3QFoXnibYwLvnOgMB9cFrt9LmOOFZVcdyCmHT03kotkn.',
        'role'     => 'commercial',
        'actif'    => 1,
        'poste'    => 'Chargée de clientèle',
    ],
    [
        'id'       => 5,
        'nom'      => 'Mehdi Ouertani',
        'email'    => 'mehdi.ouertani@bi.local',
        'password' => '$2y$12$fC66jiVdJJN/KDDU/I.Ete0Z4kMWcP5Fg4mug2MOnuTAmpUS1I2Qy',
        'role'     => 'employe',
        'actif'    => 0,   // compte désactivé — illustre le filtre actif=1 du login
        'poste'    => 'Caissier',
    ],
];

/** Libellés lisibles pour chaque rôle. */
const ROLE_LABELS = [
    'admin'      => 'Administrateur',
    'commercial' => 'Commercial',
    'employe'    => 'Employé',
];

/** Page d'atterrissage après connexion, par rôle. */
const ROLE_HOME = [
    'admin'      => 'admin.php',
    'commercial' => 'dashboard_commercial.php',
    'employe'    => 'dashboard_employe.php',
];
