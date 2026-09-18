<?php
/**
 * admin.php — Espace administrateur
 *
 * La version initiale se contentait d'intégrer une iframe Power BI.
 * Celle-ci présente une vue d'ensemble du portail, les accès aux rapports
 * reconstitués, et la note de déploiement sur les rapports réels.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/data/bi_data.php';
require_once __DIR__ . '/data/ml_data.php';
require_once __DIR__ . '/partials/layout.php';

require_role('admin');

$users    = all_users();
$actifs   = count(array_filter($users, static fn ($u) => (int) $u['actif'] === 1));
$totalCa  = col_sum(PRODUITS, 'ca');

layout_start(
    "Vue d'ensemble",
    'Administration du portail et accès aux rapports',
    'home_admin'
);
?>

<section class="kpis" aria-label="Indicateurs du portail">
  <article class="kpi" style="--accent:var(--s1)">
    <div class="lbl">CA total suivi</div>
    <div class="val"><?= e(compact_num(1_200_000)) ?><span class="u">DT</span></div>
    <div class="note">Exercices 2022 à 2024</div>
  </article>
  <article class="kpi" style="--accent:var(--s3)">
    <div class="lbl">Transactions</div>
    <div class="val"><?= e(compact_num(473_483)) ?></div>
    <div class="note">Toutes caisses confondues</div>
  </article>
  <article class="kpi" style="--accent:var(--s4)">
    <div class="lbl">Références analysées</div>
    <div class="val"><?= CLUSTERS_NB_PRODUITS ?></div>
    <div class="note">Réparties en <?= count(CLUSTERS) ?> segments</div>
  </article>
  <article class="kpi" style="--accent:var(--s7)">
    <div class="lbl">Comptes actifs</div>
    <div class="val"><?= $actifs ?><span class="u">/ <?= count($users) ?></span></div>
    <div class="note"><?= count($users) - $actifs ?> compte(s) désactivé(s)</div>
  </article>
</section>

<section class="card">
  <h2><span class="rule"></span>Rapports et analyses</h2>
  <p class="hint">Tous les rapports sont reconstitués en code : aucun accès Power BI requis.</p>

  <div class="tiles">
    <a class="tile" href="bi_ventes.php">
      <span class="ico" aria-hidden="true">◪</span>
      <span>
        <span class="t">Vue générale sur vente</span>
        <span class="d">CA, commandes, saisonnalité et série journalière</span>
      </span>
    </a>
    <a class="tile" href="bi_produits.php">
      <span class="ico" aria-hidden="true">◧</span>
      <span>
        <span class="t">Performance produits</span>
        <span class="d">Top 10, répartition du CA, volumes de commandes</span>
      </span>
    </a>
    <a class="tile" href="bi_paiements.php">
      <span class="ico" aria-hidden="true">◨</span>
      <span>
        <span class="t">Analyse des paiements</span>
        <span class="d">Modes d'encaissement, recouvrement, écarts de caisse</span>
      </span>
    </a>
    <a class="tile" href="dashboard_ml.php">
      <span class="ico" aria-hidden="true">◈</span>
      <span>
        <span class="t">Prédiction & Data Mining</span>
        <span class="d">Prévision de CA, segmentation, analyse du panier</span>
      </span>
    </a>
    <a class="tile" href="config_employes.php">
      <span class="ico" aria-hidden="true">◉</span>
      <span>
        <span class="t">Gestion des utilisateurs</span>
        <span class="d">Comptes, rôles et activation</span>
      </span>
    </a>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════════════
     Note de déploiement — le point central de cette version
     ══════════════════════════════════════════════════════════════════════ -->
<section class="card">
  <h2><span class="rule" style="background:var(--s2)"></span>Note de déploiement</h2>
  <p class="hint">À lire avant toute mise en ligne.</p>

  <div class="alert alert-warn">
    <span class="ico" aria-hidden="true">⚠</span>
    <span>
      <strong>Les rapports Power BI réels ne doivent pas être déployés sur ce portail.</strong>
      La version initiale intégrait trois rapports via
      <code class="inline">app.powerbi.com/reportEmbed?reportId=…&amp;autoAuth=true</code>.
      Ce montage pose trois problèmes en production :
      <br><br>
      <strong>1. Il ne fonctionne pas pour les visiteurs.</strong> Le paramètre
      <code class="inline">autoAuth=true</code> exige une session Microsoft Entra ID
      active dans le locataire propriétaire du rapport. Tout autre visiteur voit
      un écran de connexion Microsoft, puis une erreur d'autorisation.
      <br><br>
      <strong>2. Il expose l'organisation.</strong> Les identifiants de rapport et
      de locataire (<code class="inline">ctid</code>) figurent en clair dans le code
      source publié sur GitHub. Ce sont des références internes qui n'ont rien à
      faire dans un dépôt public.
      <br><br>
      <strong>3. Il expose des données d'entreprise.</strong> Un rapport correctement
      publié pour un public externe passe par « Publier sur le web », qui rend le
      rapport <em>accessible à tout internaute disposant du lien</em> — sans
      authentification. Ce mode est à proscrire pour des données de vente.
      <br><br>
      C'est la raison d'être de cette version : les pages
      <code class="inline">bi_ventes.php</code>, <code class="inline">bi_produits.php</code>
      et <code class="inline">bi_paiements.php</code> reproduisent les mêmes analyses
      en code, à partir de données figées. Elles attestent du travail Power BI
      sans rien exposer.
    </span>
  </div>

  <div class="tbl-wrap" style="margin-top:18px">
    <table class="tbl">
      <caption class="sr-only">Correspondance entre les rapports Power BI et les pages du portail</caption>
      <thead>
        <tr>
          <th scope="col">Rapport Power BI d'origine</th>
          <th scope="col">Page de remplacement</th>
          <th scope="col">État</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <th scope="row" style="font-weight:500">Vue générale sur vente</th>
          <td><code class="inline">bi_ventes.php</code></td>
          <td><span class="badge badge-green">Reconstituée</span></td>
        </tr>
        <tr>
          <th scope="row" style="font-weight:500">Performance produits</th>
          <td><code class="inline">bi_produits.php</code></td>
          <td><span class="badge badge-green">Reconstituée</span></td>
        </tr>
        <tr>
          <th scope="row" style="font-weight:500">Analyse des paiements</th>
          <td><code class="inline">bi_paiements.php</code></td>
          <td><span class="badge badge-green">Reconstituée</span></td>
        </tr>
        <tr>
          <th scope="row" style="font-weight:500">Performance employés</th>
          <td style="color:var(--ink-muted)">—</td>
          <td><span class="badge badge-slate">Non reprise</span></td>
        </tr>
        <tr>
          <th scope="row" style="font-weight:500">Intégrations <code class="inline">reportEmbed</code></th>
          <td style="color:var(--ink-muted)">Supprimées</td>
          <td><span class="badge badge-red">À ne pas déployer</span></td>
        </tr>
      </tbody>
    </table>
  </div>
</section>

<?php layout_end(); ?>
