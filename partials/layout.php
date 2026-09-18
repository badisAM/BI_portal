<?php
/**
 * partials/layout.php — Gabarit commun à toutes les pages authentifiées.
 *
 * Usage dans une page :
 *     require_once __DIR__ . '/auth.php';
 *     require_role('admin');
 *     layout_start('Titre de la page', 'Sous-titre', 'cle_menu');
 *     ... contenu ...
 *     layout_end();
 */

declare(strict_types=1);

/**
 * Entrées du menu latéral, filtrées par rôle.
 *
 * @return array<int, array{key:string,href:string,ico:string,label:string,group:string}>
 */
function nav_items(string $role): array
{
    $all = [
        // Accueil propre à chaque rôle
        ['key' => 'home_admin',      'href' => 'admin.php',                'ico' => '▦', 'label' => "Vue d'ensemble",       'group' => 'Pilotage', 'roles' => ['admin']],
        ['key' => 'home_commercial', 'href' => 'dashboard_commercial.php', 'ico' => '▦', 'label' => 'Mon tableau de bord',  'group' => 'Pilotage', 'roles' => ['commercial']],
        ['key' => 'home_employe',    'href' => 'dashboard_employe.php',    'ico' => '▦', 'label' => 'Mon tableau de bord',  'group' => 'Pilotage', 'roles' => ['employe']],

        // Réplication des rapports Power BI
        ['key' => 'bi_ventes',    'href' => 'bi_ventes.php',    'ico' => '◪', 'label' => 'Vue générale ventes',  'group' => 'Rapports', 'roles' => ['admin', 'commercial', 'employe']],
        ['key' => 'bi_produits',  'href' => 'bi_produits.php',  'ico' => '◧', 'label' => 'Performance produits', 'group' => 'Rapports', 'roles' => ['admin', 'commercial', 'employe']],
        ['key' => 'bi_paiements', 'href' => 'bi_paiements.php', 'ico' => '◨', 'label' => 'Analyse des paiements','group' => 'Rapports', 'roles' => ['admin', 'commercial']],

        // Data science
        ['key' => 'ml', 'href' => 'dashboard_ml.php', 'ico' => '◈', 'label' => 'Prédiction & Data Mining', 'group' => 'Data Science', 'roles' => ['admin', 'commercial']],

        // Administration
        ['key' => 'users', 'href' => 'config_employes.php', 'ico' => '◉', 'label' => 'Utilisateurs', 'group' => 'Administration', 'roles' => ['admin']],
    ];

    return array_values(array_filter(
        $all,
        static fn (array $i): bool => in_array($role, $i['roles'], true)
    ));
}

/** Initiales pour l'avatar. */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return $out !== '' ? $out : '?';
}

/**
 * Ouvre la page : <head>, bandeau démo, menu latéral, barre supérieure.
 */
function layout_start(string $title, string $subtitle = '', string $active = ''): void
{
    $role = current_role();
    $name = current_name();
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> — <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/theme.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%2310355c'/><rect x='7' y='15' width='4' height='10' rx='1.4' fill='%232a78d6'/><rect x='14' y='10' width='4' height='15' rx='1.4' fill='%235598e7'/><rect x='21' y='6' width='4' height='19' rx='1.4' fill='%2386b6ef'/></svg>">
<script src="assets/vendor/chart.umd.js"></script>
<!-- chargé sans « defer » : les scripts des pages, placés dans le corps,
     s'exécutent pendant l'analyse du document et ont besoin des aides
     (BIFmt, BIAxisY…) définies ici. -->
<script src="assets/charts.js"></script>
</head>
<body>

<?php if (DEMO_MODE): ?>
<!-- ══════════════════════════════════════════════════════════════════════
     AVERTISSEMENT DE DÉPLOIEMENT
     Ce bandeau est la réponse à la règle : les rapports Power BI réels ne
     doivent jamais partir en production dans ce portail.
     ══════════════════════════════════════════════════════════════════════ -->
<div class="demo-banner" role="status">
  <span class="ico" aria-hidden="true">⚠</span>
  <span>
    <strong>Version de démonstration — données statiques.</strong>
    Les rapports affichés sont une <strong>reconstitution en code</strong> des tableaux
    de bord Power BI du projet. Les rapports Power BI réels
    (<code class="inline">app.powerbi.com/reportEmbed</code>) ne doivent
    <strong>pas</strong> être déployés sur ce portail : ils exposent des données
    d'entreprise et exigent une authentification Microsoft Entra ID nominative.
  </span>
</div>
<?php endif; ?>

<div class="shell">

  <aside class="sidebar">
    <div class="brand">
      <div class="mark"><span class="glyph">BI</span> <?= e(APP_NAME) ?></div>
      <div class="tag"><?= e(APP_BASELINE) ?></div>
    </div>

    <nav class="nav" aria-label="Navigation principale">
      <?php
      $lastGroup = null;
      foreach (nav_items($role) as $item):
          if ($item['group'] !== $lastGroup):
              $lastGroup = $item['group']; ?>
              <div class="group"><?= e($item['group']) ?></div>
          <?php endif; ?>
          <a href="<?= e($item['href']) ?>"
             class="<?= $active === $item['key'] ? 'active' : '' ?>"
             <?= $active === $item['key'] ? 'aria-current="page"' : '' ?>>
            <span class="ico" aria-hidden="true"><?= $item['ico'] ?></span>
            <?= e($item['label']) ?>
          </a>
      <?php endforeach; ?>
    </nav>

    <div class="foot">
      <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?><br>
      Mode démonstration
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div>
        <h1><?= e($title) ?></h1>
        <?php if ($subtitle !== ''): ?><div class="sub"><?= e($subtitle) ?></div><?php endif; ?>
      </div>
      <div class="who">
        <div class="avatar" aria-hidden="true"><?= e(initials($name)) ?></div>
        <div class="meta">
          <div class="n"><?= e($name) ?></div>
          <div class="r"><?= e(ROLE_LABELS[$role] ?? $role) ?></div>
        </div>
        <a class="out" href="logout.php">Déconnexion</a>
      </div>
    </header>

    <main class="page">
<?php
    // Message affiché quand un utilisateur tente d'ouvrir une page
    // réservée à un autre rôle.
    if (isset($_GET['refus'])): ?>
      <div class="alert alert-warn">
        <span class="ico" aria-hidden="true">⚠</span>
        <span>Votre rôle (<strong><?= e(ROLE_LABELS[$role] ?? $role) ?></strong>)
        ne donne pas accès à la page demandée.</span>
      </div>
    <?php endif;
}

/** Ferme la page. */
function layout_end(string $note = ''): void
{
    ?>
    <?php if ($note !== ''): ?>
      <p class="foot-note"><?= $note ?></p>
    <?php endif; ?>
    </main>
  </div>
</div>
</body>
</html>
<?php
}
