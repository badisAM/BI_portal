<?php
/**
 * login.php — Page de connexion
 *
 * ── Ce qui a été corrigé par rapport à la version initiale ────────────────
 *
 *  Avant :
 *      $email = $_POST['email'];
 *      $password = md5($_POST['password']);
 *      $sql = "SELECT * FROM users WHERE email='$email' AND password='$password'";
 *
 *  Deux failles dans trois lignes :
 *
 *   1. Injection SQL. La valeur saisie était collée dans la requête sans
 *      échappement. Saisir  ' OR '1'='1' --  dans le champ email ouvrait
 *      la session du premier utilisateur de la table, sans mot de passe.
 *
 *   2. MD5. Cet algorithme n'est plus considéré comme sûr depuis 2005 :
 *      une empreinte MD5 de mot de passe courant se retrouve en clair en
 *      quelques secondes sur n'importe quelle table arc-en-ciel publique.
 *
 *  Maintenant : plus de SQL du tout (annuaire statique), et les mots de
 *  passe sont vérifiés par password_verify() sur des empreintes bcrypt.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// Déjà connecté : on renvoie directement vers l'espace du rôle.
if (is_logged_in()) {
    header('Location: ' . (ROLE_HOME[current_role()] ?? 'admin.php'));
    exit;
}

$message = '';

if (isset($_POST['login'])) {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $message = 'Session expirée. Merci de réessayer.';
    } else {
        [$ok, $message] = attempt_login(
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );

        if ($ok) {
            header('Location: ' . (ROLE_HOME[current_role()] ?? 'admin.php'));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Connexion — <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/theme.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%2310355c'/><rect x='7' y='15' width='4' height='10' rx='1.4' fill='%232a78d6'/><rect x='14' y='10' width='4' height='15' rx='1.4' fill='%235598e7'/><rect x='21' y='6' width='4' height='19' rx='1.4' fill='%2386b6ef'/></svg>">
</head>
<body>

<?php if (DEMO_MODE): ?>
<div class="demo-banner" role="status">
  <span class="ico" aria-hidden="true">⚠</span>
  <span>
    <strong>Version de démonstration.</strong> Portail à données statiques :
    ni base MySQL, ni Python, ni rapport Power BI intégré. Les tableaux de bord
    Power BI réels ne doivent pas être déployés sur cette instance.
  </span>
</div>
<?php endif; ?>

<div class="login-wrap">

  <!-- Colonne de présentation -->
  <aside class="login-art">
    <h2><?= e(APP_NAME) ?> — pilotage de l'activité</h2>
    <p>
      Rapports de vente, performance produits, analyse des encaissements et
      modèles prédictifs, réunis dans une seule interface.
    </p>
    <ul>
      <li><span class="b" aria-hidden="true">▸</span> Vue générale des ventes 2022 – 2024</li>
      <li><span class="b" aria-hidden="true">▸</span> Performance détaillée par référence</li>
      <li><span class="b" aria-hidden="true">▸</span> Analyse des modes de paiement</li>
      <li><span class="b" aria-hidden="true">▸</span> Prévision de CA et segmentation produits</li>
      <li><span class="b" aria-hidden="true">▸</span> Analyse du panier (règles d'association)</li>
    </ul>
  </aside>

  <!-- Colonne formulaire -->
  <main class="login-form">
    <div class="inner">
      <h1>Connexion</h1>
      <p class="lead">Accédez à votre espace d'analyse.</p>

      <?php if ($message !== ''): ?>
        <div class="alert alert-err" style="margin-bottom:17px">
          <span class="ico" aria-hidden="true">⚠</span>
          <span><?= e($message) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="field">
          <label for="email">Adresse email</label>
          <input type="email" name="email" id="email" required autofocus
                 autocomplete="username"
                 value="<?= e((string) ($_POST['email'] ?? '')) ?>">
        </div>

        <div class="field">
          <label for="password">Mot de passe</label>
          <input type="password" name="password" id="password" required
                 autocomplete="current-password">
        </div>

        <button class="btn" type="submit" name="login">Se connecter</button>
      </form>

      <?php if (DEMO_MODE): ?>
      <!-- ══════════════════════════════════════════════════════════════
           Comptes de démonstration.
           À SUPPRIMER en usage réel, en même temps que les hashes de
           data/users.php.
           ══════════════════════════════════════════════════════════════ -->
      <div class="accounts">
        <div class="h">Comptes de démonstration — cliquez pour remplir</div>

        <button type="button" class="row" data-mail="admin@bi.local" data-pass="admin123">
          <span><strong>Administrateur</strong><br><code>admin@bi.local</code></span>
          <span class="badge badge-blue">admin123</span>
        </button>

        <button type="button" class="row" data-mail="commercial@bi.local" data-pass="commercial123">
          <span><strong>Commercial</strong><br><code>commercial@bi.local</code></span>
          <span class="badge badge-green">commercial123</span>
        </button>

        <button type="button" class="row" data-mail="employe@bi.local" data-pass="employe123">
          <span><strong>Employé</strong><br><code>employe@bi.local</code></span>
          <span class="badge badge-violet">employe123</span>
        </button>

        <p class="foot-note" style="margin-top:11px">
          Chaque rôle ouvre un périmètre différent : l'employé n'accède ni aux
          paiements, ni aux modèles, ni à la gestion des comptes.
        </p>
      </div>

      <script>
        // Pré-remplissage des identifiants de démonstration.
        document.querySelectorAll('.accounts .row').forEach(function (b) {
          b.addEventListener('click', function () {
            document.getElementById('email').value    = b.dataset.mail;
            document.getElementById('password').value = b.dataset.pass;
            document.getElementById('password').focus();
          });
        });
      </script>
      <?php endif; ?>
    </div>
  </main>

</div>
</body>
</html>
