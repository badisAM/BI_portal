<?php
/**
 * dashboard_employe.php — Espace employé
 *
 * Périmètre volontairement réduit : l'employé consulte l'activité et les
 * produits, mais n'accède ni aux encaissements, ni aux modèles, ni à la
 * gestion des comptes. C'est le rôle qui démontre que le cloisonnement
 * fonctionne.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/data/bi_data.php';
require_once __DIR__ . '/partials/layout.php';

require_role('employe');

$produits = PRODUITS;
$totalCa  = col_sum($produits, 'ca');
$totalQte = col_sum($produits, 'qte');
$hex      = serie_hex();

// Produit le plus demandé en fréquence (≠ meilleur en chiffre d'affaires).
$parCmd = $produits;
usort($parCmd, static fn ($a, $b) => $b['commandes'] <=> $a['commandes']);

layout_start(
    'Mon tableau de bord',
    'Activité du point de vente — ' . current_name(),
    'home_employe'
);
?>

<section class="kpis" aria-label="Indicateurs d'activité">
  <article class="kpi" style="--accent:var(--s1)">
    <div class="lbl">Quantités vendues</div>
    <div class="val"><?= e(compact_num($totalQte)) ?><span class="u">u.</span></div>
    <div class="note">Top 10 des références</div>
  </article>
  <article class="kpi" style="--accent:var(--s3)">
    <div class="lbl">Référence la plus demandée</div>
    <div class="val" style="font-size:1.3rem"><?= e($parCmd[0]['nom']) ?></div>
    <div class="note"><?= money((float) $parCmd[0]['commandes'], 0) ?> commandes</div>
  </article>
  <article class="kpi" style="--accent:var(--s4)">
    <div class="lbl">Période la plus chargée</div>
    <div class="val" style="font-size:1.3rem">Déc.</div>
    <div class="note">1,6 fois un mois moyen</div>
  </article>
  <article class="kpi" style="--accent:var(--s7)">
    <div class="lbl">Jour de pointe</div>
    <div class="val" style="font-size:1.3rem">Samedi</div>
    <div class="note">Creux le dimanche</div>
  </article>
</section>

<div class="grid g-2">

  <section class="card">
    <h2><span class="rule"></span>Références les plus demandées</h2>
    <p class="hint">
      Classement par fréquence d'achat — utile pour le réassort, à ne pas
      confondre avec le classement par chiffre d'affaires.
    </p>

    <div class="hbars">
      <?php
      $maxCmd = $parCmd[0]['commandes'];
      foreach (array_slice($parCmd, 0, 6) as $i => $p): ?>
        <div class="hbar">
          <span class="lab" title="<?= e($p['nom']) ?>"><?= e($p['nom']) ?></span>
          <span class="track">
            <span class="fill" style="width:<?= round($p['commandes'] / $maxCmd * 100, 2) ?>%"></span>
          </span>
          <span class="val"><?= money((float) $p['commandes'], 0) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <h2><span class="rule" style="background:var(--s3)"></span>Rythme de la semaine</h2>
    <p class="hint">Part du chiffre d'affaires par jour, moyenne sur trois ans.</p>

    <div class="chart" style="height:225px">
      <canvas id="cSemaine" aria-label="Répartition du chiffre d'affaires par jour de la semaine" role="img"></canvas>
    </div>

    <p class="foot-note" style="margin-top:12px">
      Le vendredi et le samedi concentrent l'essentiel de l'activité ; le
      dimanche est le jour le plus calme.
    </p>
  </section>

  <section class="card span-all">
    <h2><span class="rule" style="background:var(--s7)"></span>Mes rapports</h2>
    <div class="tiles">
      <a class="tile" href="bi_ventes.php">
        <span class="ico" aria-hidden="true">◪</span>
        <span><span class="t">Vue générale sur vente</span>
        <span class="d">Activité 2022 – 2024</span></span>
      </a>
      <a class="tile" href="bi_produits.php">
        <span class="ico" aria-hidden="true">◧</span>
        <span><span class="t">Performance produits</span>
        <span class="d">Détail par référence</span></span>
      </a>
    </div>

    <div class="alert alert-info" style="margin-top:16px">
      <span class="ico" aria-hidden="true">ℹ</span>
      <span>
        L'analyse des paiements, les modèles prédictifs et la gestion des
        comptes sont réservés aux rôles commercial et administrateur.
      </span>
    </div>
  </section>

</div>

<script>
/* ── Répartition hebdomadaire ─────────────────────────────────────────── */
(function () {
  // Coefficients issus de la série journalière de data/bi_data.php.
  const jours  = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
  const coefs  = [0.82, 0.86, 0.92, 1.02, 1.28, 1.34, 0.76];
  const total  = coefs.reduce((s, c) => s + c, 0);
  const parts  = coefs.map((c) => c / total * 100);

  new Chart(document.getElementById('cSemaine'), {
    type: 'bar',
    data: {
      labels: jours,
      datasets: [{
        label: 'Part du CA',
        data: parts,
        // Une seule série : une teinte unique, l'intensité ne code rien.
        backgroundColor: '#1baf7a',
        hoverBackgroundColor: '#169268',
        borderRadius: { topLeft: 4, topRight: 4 },
        borderSkipped: 'bottom',
        maxBarThickness: 40,
      }],
    },
    options: {
      plugins: {
        tooltip: {
          callbacks: { label: (c) => ' ' + BIFmt.pct(c.parsed.y, 1) + ' du CA hebdomadaire' },
        },
      },
      scales: {
        x: BIAxisX({ ticks: { color: '#7a8699', padding: 6, maxRotation: 0, font: { size: 10 } } }),
        y: BIAxisY({ ticks: { color: '#7a8699', padding: 8, callback: (v) => v + ' %' } }),
      },
    },
  });
})();
</script>

<?php layout_end(); ?>
