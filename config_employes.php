<?php
/**
 * config_employes.php — Gestion des utilisateurs
 *
 * ── Ce qui a été corrigé ──────────────────────────────────────────────────
 *
 *  Avant :
 *      $id = (int)$_GET['toggle'];
 *      $conn->query("UPDATE users SET actif = 1-actif WHERE id=$id");
 *
 *  Le transtypage en (int) protégeait bien de l'injection SQL, mais
 *  l'action restait déclenchable par un simple lien GET. Une image
 *  <img src="…/config_employes.php?toggle=1"> placée sur une autre page
 *  suffisait à désactiver un compte à l'insu de l'administrateur connecté
 *  (falsification de requête inter-sites, ou CSRF).
 *
 *  Maintenant : l'action passe par un formulaire POST accompagné d'un
 *  jeton de session, et un administrateur ne peut pas se désactiver.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/partials/layout.php';

require_role('admin');

$info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle'])) {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $info = 'Session expirée — aucune modification effectuée.';
    } else {
        $id   = (int) $_POST['toggle'];
        $cible = find_user_by_id($id);

        if ($cible === null) {
            $info = 'Utilisateur introuvable.';
        } elseif ($cible['role'] === 'admin') {
            $info = 'Un compte administrateur ne peut pas être désactivé.';
        } else {
            toggle_user($id);
            $apres = find_user_by_id($id);
            $info  = sprintf(
                'Compte « %s » %s.',
                $apres['nom'],
                (int) $apres['actif'] === 1 ? 'activé' : 'désactivé'
            );
        }
    }
}

$users  = all_users();
$actifs = count(array_filter($users, static fn ($u) => (int) $u['actif'] === 1));

// Décompte par rôle, pour le bandeau d'indicateurs.
$parRole = [];
foreach ($users as $u) {
    $parRole[$u['role']] = ($parRole[$u['role']] ?? 0) + 1;
}

layout_start(
    'Gestion des utilisateurs',
    'Comptes, rôles et droits d\'accès',
    'users'
);
?>

<?php if ($info !== ''): ?>
  <div class="alert alert-info">
    <span class="ico" aria-hidden="true">ℹ</span>
    <span><?= e($info) ?></span>
  </div>
<?php endif; ?>

<section class="kpis" aria-label="Répartition des comptes">
  <article class="kpi" style="--accent:var(--s1)">
    <div class="lbl">Comptes</div>
    <div class="val"><?= count($users) ?></div>
    <div class="note"><?= $actifs ?> actif(s), <?= count($users) - $actifs ?> désactivé(s)</div>
  </article>
  <?php
  $accents = ['admin' => 'var(--s7)', 'commercial' => 'var(--s3)', 'employe' => 'var(--s4)'];
  foreach (ROLE_LABELS as $role => $label): ?>
    <article class="kpi" style="--accent:<?= e($accents[$role] ?? 'var(--s1)') ?>">
      <div class="lbl"><?= e($label) ?></div>
      <div class="val"><?= (int) ($parRole[$role] ?? 0) ?></div>
      <div class="note"><?= $role === 'admin' ? 'Accès complet' : ($role === 'commercial' ? 'Rapports + modèles' : 'Rapports de vente') ?></div>
    </article>
  <?php endforeach; ?>
</section>

<section class="card">
  <h2><span class="rule"></span>Liste des comptes</h2>
  <p class="hint">
    Les administrateurs figurent dans la liste mais ne sont pas désactivables,
    afin de ne pas se verrouiller hors du portail.
  </p>

  <div class="tbl-wrap">
    <table class="tbl">
      <caption class="sr-only">Comptes utilisateurs du portail</caption>
      <thead>
        <tr>
          <th scope="col">Nom</th>
          <th scope="col">Email</th>
          <th scope="col">Fonction</th>
          <th scope="col">Rôle</th>
          <th scope="col">État</th>
          <th scope="col">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u):
            $estMoi  = (int) $u['id'] === (int) ($_SESSION['id'] ?? 0);
            $estAdmin = $u['role'] === 'admin';
            $badge = ['admin' => 'badge-violet', 'commercial' => 'badge-green', 'employe' => 'badge-blue'][$u['role']] ?? 'badge-slate';
        ?>
        <tr>
          <th scope="row" style="font-weight:600">
            <?= e($u['nom']) ?>
            <?php if ($estMoi): ?>
              <span class="badge badge-slate" style="margin-left:5px">vous</span>
            <?php endif; ?>
          </th>
          <td style="color:var(--ink-2)"><?= e($u['email']) ?></td>
          <td style="color:var(--ink-muted)"><?= e($u['poste']) ?></td>
          <td><span class="badge <?= $badge ?>"><?= e(ROLE_LABELS[$u['role']] ?? $u['role']) ?></span></td>
          <td>
            <?php if ((int) $u['actif'] === 1): ?>
              <span class="badge badge-green">Actif</span>
            <?php else: ?>
              <span class="badge badge-red">Inactif</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($estAdmin): ?>
              <span style="color:var(--ink-muted);font-size:.765rem">Protégé</span>
            <?php else: ?>
              <!-- POST + jeton : l'action ne peut plus être déclenchée
                   depuis un site tiers. -->
              <form method="POST" action="" style="display:inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="toggle" value="<?= (int) $u['id'] ?>">
                <button type="submit"
                        class="btn btn-sm <?= (int) $u['actif'] === 1 ? 'btn-danger' : 'btn-ok' ?>">
                  <?= (int) $u['actif'] === 1 ? 'Désactiver' : 'Activer' ?>
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="alert alert-warn" style="margin-top:18px">
    <span class="ico" aria-hidden="true">⚠</span>
    <span>
      <strong>Portée des modifications.</strong> En mode démonstration, les
      activations et désactivations sont conservées dans votre session et
      remises à zéro à la déconnexion. Le portail ne peut donc pas écrire sur le
      disque du serveur — ce qui lui permet de tourner sur un hébergement en
      lecture seule. Pour rendre les changements permanents, remplacez
      <code class="inline">toggle_user()</code> dans
      <code class="inline">auth.php</code> par une écriture en base de données.
    </span>
  </div>
</section>

<section class="card">
  <h2><span class="rule" style="background:var(--s3)"></span>Droits par rôle</h2>
  <p class="hint">Ce que chaque rôle peut ouvrir dans le portail.</p>

  <div class="tbl-wrap">
    <table class="tbl">
      <caption class="sr-only">Matrice des droits d'accès</caption>
      <thead>
        <tr>
          <th scope="col">Page</th>
          <th scope="col">Administrateur</th>
          <th scope="col">Commercial</th>
          <th scope="col">Employé</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $matrice = [
            'Vue générale sur vente'    => ['admin', 'commercial', 'employe'],
            'Performance produits'      => ['admin', 'commercial', 'employe'],
            'Analyse des paiements'     => ['admin', 'commercial'],
            'Prédiction & Data Mining'  => ['admin', 'commercial'],
            'Gestion des utilisateurs'  => ['admin'],
        ];
        foreach ($matrice as $page => $autorises): ?>
        <tr>
          <th scope="row" style="font-weight:500"><?= e($page) ?></th>
          <?php foreach (['admin', 'commercial', 'employe'] as $r): ?>
            <td>
              <?php if (in_array($r, $autorises, true)): ?>
                <span class="badge badge-green">✓ Autorisé</span>
              <?php else: ?>
                <span class="badge badge-slate">— Refusé</span>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php layout_end(); ?>
