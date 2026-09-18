<?php
/**
 * dashboard_commercial.php — Espace commercial
 *
 * Remplace l'iframe Power BI par une synthèse commerciale et les accès
 * aux rapports auxquels ce rôle a droit.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/data/bi_data.php';
require_once __DIR__ . '/data/ml_data.php';
require_once __DIR__ . '/partials/layout.php';

require_role('commercial');

$produits = PRODUITS;
$totalCa  = col_sum($produits, 'ca');
$top3     = array_slice($produits, 0, 3);
$hex      = serie_hex();

// Meilleure opportunité de vente croisée : la règle au lift le plus élevé.
$regles = REGLES;
usort($regles, static fn ($a, $b) => $b['lift'] <=> $a['lift']);
$meilleureRegle = $regles[0];

layout_start(
    'Mon tableau de bord',
    'Synthèse commerciale — ' . current_name(),
    'home_commercial'
);
?>

<section class="kpis" aria-label="Indicateurs commerciaux">
  <article class="kpi" style="--accent:var(--s1)">
    <div class="lbl">CA total</div>
    <div class="val"><?= e(compact_num(1_200_000)) ?><span class="u">DT</span></div>
    <div class="note up">▲ Croissance annuelle 0,94 %</div>
  </article>
  <article class="kpi" style="--accent:var(--s3)">
    <div class="lbl">Panier moyen</div>
    <div class="val"><?= money(2.85, 2) ?><span class="u">DT</span></div>
    <div class="note">Sur <?= e(compact_num(420_000)) ?> commandes</div>
  </article>
  <article class="kpi" style="--accent:var(--s4)">
    <div class="lbl">Meilleur produit</div>
    <div class="val" style="font-size:1.3rem"><?= e($produits[0]['nom']) ?></div>
    <div class="note"><?= money($produits[0]['ca'] / $totalCa * 100, 1) ?> % du CA du top 10</div>
  </article>
  <article class="kpi" style="--accent:var(--s2)">
    <div class="lbl">Trimestre le plus fort</div>
    <div class="val">T4</div>
    <div class="note"><?= money(VENTES_TRIMESTRES['T4'] / array_sum(VENTES_TRIMESTRES) * 100, 0) ?> % du CA annuel</div>
  </article>
</section>

<div class="grid g-2">

  <section class="card">
    <h2><span class="rule"></span>Trois premières références</h2>
    <p class="hint">Contribution au chiffre d'affaires du top 10.</p>

    <div class="hbars">
      <?php foreach ($top3 as $i => $p): ?>
        <div class="hbar">
          <span class="lab"><?= e($p['nom']) ?></span>
          <span class="track">
            <span class="fill" style="width:<?= round($p['ca'] / $produits[0]['ca'] * 100, 2) ?>%"></span>
          </span>
          <span class="val"><?= money($p['ca'], 0) ?> DT</span>
        </div>
      <?php endforeach; ?>
    </div>

    <p class="foot-note" style="margin-top:14px">
      Détail complet dans <a href="bi_produits.php" style="color:var(--s1);font-weight:600">Performance produits</a>.
    </p>
  </section>

  <section class="card">
    <h2><span class="rule" style="background:var(--s2)"></span>Opportunité de vente croisée</h2>
    <p class="hint">Association la plus forte détectée sur les tickets de caisse.</p>

    <div class="kpi" style="--accent:var(--s2)">
      <div class="lbl">Règle la plus forte</div>
      <div class="val" style="font-size:1.12rem;line-height:1.35">
        <?= e($meilleureRegle['si']) ?>
        <span style="color:var(--ink-muted);font-weight:500"> → </span>
        <?= e($meilleureRegle['alors']) ?>
      </div>
      <div class="note">
        Lift <?= money($meilleureRegle['lift'], 2) ?> &middot;
        Confiance <?= money($meilleureRegle['confiance'] * 100, 1) ?> %
      </div>
    </div>

    <p class="foot-note" style="margin-top:14px">
      Un client qui prend <strong><?= e($meilleureRegle['si']) ?></strong> a
      <?= money($meilleureRegle['confiance'] * 100, 1) ?> % de chances de prendre aussi
      <strong><?= e($meilleureRegle['alors']) ?></strong> — soit
      <?= money($meilleureRegle['lift'], 1) ?> fois plus que le hasard.
      Les autres règles sont dans
      <a href="dashboard_ml.php" style="color:var(--s1);font-weight:600">Prédiction & Data Mining</a>.
    </p>
  </section>

  <section class="card span-all">
    <h2><span class="rule" style="background:var(--s7)"></span>Mes rapports</h2>
    <div class="tiles">
      <a class="tile" href="bi_ventes.php">
        <span class="ico" aria-hidden="true">◪</span>
        <span><span class="t">Vue générale sur vente</span>
        <span class="d">CA, commandes et saisonnalité 2022 – 2024</span></span>
      </a>
      <a class="tile" href="bi_produits.php">
        <span class="ico" aria-hidden="true">◧</span>
        <span><span class="t">Performance produits</span>
        <span class="d">Top 10 et répartition du chiffre d'affaires</span></span>
      </a>
      <a class="tile" href="bi_paiements.php">
        <span class="ico" aria-hidden="true">◨</span>
        <span><span class="t">Analyse des paiements</span>
        <span class="d">Modes d'encaissement et recouvrement</span></span>
      </a>
      <a class="tile" href="dashboard_ml.php">
        <span class="ico" aria-hidden="true">◈</span>
        <span><span class="t">Prédiction & Data Mining</span>
        <span class="d">Prévision de CA et analyse du panier</span></span>
      </a>
    </div>
  </section>

</div>

<?php layout_end(); ?>
