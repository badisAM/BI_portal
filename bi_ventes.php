<?php
/**
 * bi_ventes.php — Réplication en code du rapport « Vue générale sur vente »
 *
 * Reproduit la page Power BI d'origine : bandeau d'indicateurs, CA par
 * trimestre, CA mensuel comparé sur trois années, et série journalière.
 *
 * Aucune iframe, aucune licence, aucune authentification Microsoft :
 * la page est autonome et s'affiche pour tout visiteur connecté.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/data/bi_data.php';
require_once __DIR__ . '/partials/layout.php';

require_role(['admin', 'commercial', 'employe']);

$journalier = ca_journalier();
$hex        = serie_hex();

layout_start(
    'Vue générale sur vente',
    'Rapport Power BI reconstitué en code — activité 2022 à 2024',
    'bi_ventes'
);
?>

<!-- Segments repris du rapport d'origine (présentation seule) -->
<div class="filters">
  <span class="fl">Période</span>
  <span class="chips"><span class="chip on"><?= e(PRODUITS_FILTRES['periode']) ?></span></span>
  <span class="fl">Années</span>
  <span class="chips">
    <?php foreach (array_keys(VENTES_MENSUEL) as $a): ?>
      <span class="chip on"><?= e((string) $a) ?></span>
    <?php endforeach; ?>
  </span>
</div>

<!-- ════════════════════════════════════════════════════════════════════
     Bandeau d'indicateurs
     ════════════════════════════════════════════════════════════════════ -->
<section class="kpis" aria-label="Indicateurs clés">
  <?php foreach (VENTES_KPI as $k): ?>
    <article class="kpi" style="--accent:<?= e($k['accent']) ?>">
      <div class="lbl"><?= e($k['lbl']) ?></div>
      <div class="val">
        <?= e(kpi_value($k)) ?><?php if ($k['unit'] !== ''): ?><span class="u"><?= e($k['unit']) ?></span><?php endif; ?>
      </div>
      <div class="note <?= isset($k['trend']) ? e($k['trend']) : '' ?>">
        <?= isset($k['trend']) ? ($k['trend'] === 'up' ? '▲ ' : '▼ ') : '' ?><?= e($k['note']) ?>
      </div>
    </article>
  <?php endforeach; ?>
</section>

<div class="grid g-2">

  <!-- ══════════════════════════════════════════════════════════════════
       CA par trimestre
       ══════════════════════════════════════════════════════════════════ -->
  <section class="card">
    <h2><span class="rule"></span>CA total par trimestre</h2>
    <p class="hint">Le quatrième trimestre concentre 38 % du chiffre d'affaires annuel.</p>
    <div class="chart"><canvas id="cTrim" aria-label="CA total par trimestre" role="img"></canvas></div>

    <!-- Doublon tabulaire : la lecture ne dépend jamais du seul graphique -->
    <div class="tbl-wrap" style="margin-top:14px">
      <table class="tbl">
        <caption class="sr-only">CA par trimestre, en dinars</caption>
        <thead><tr><th scope="col">Trimestre</th><th scope="col" class="num">CA (DT)</th><th scope="col" class="num">Part</th></tr></thead>
        <tbody>
          <?php $totTrim = array_sum(VENTES_TRIMESTRES); ?>
          <?php foreach (VENTES_TRIMESTRES as $t => $v): ?>
          <tr>
            <th scope="row" style="font-weight:500"><?= e($t) ?></th>
            <td class="num"><?= money((float) $v, 0) ?></td>
            <td class="num"><?= money($v / $totTrim * 100, 1) ?> %</td>
          </tr>
          <?php endforeach; ?>
          <tr class="total"><td>Total</td><td class="num"><?= money((float) $totTrim, 0) ?></td><td class="num">100,0 %</td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <!-- ══════════════════════════════════════════════════════════════════
       CA mensuel comparé
       ══════════════════════════════════════════════════════════════════ -->
  <section class="card">
    <h2><span class="rule" style="background:var(--s2)"></span>CA mensuel par année</h2>
    <p class="hint">
      Mois remis dans l'ordre chronologique — le rapport d'origine les triait
      par ordre alphabétique, ce qui masquait la saisonnalité.
    </p>
    <div class="chart"><canvas id="cMois" aria-label="CA mensuel comparé par année" role="img"></canvas></div>
    <div class="legend" id="lMois"></div>

</section>

  <!-- ══════════════════════════════════════════════════════════════════
       Détail mensuel — carte pleine largeur : quatorze colonnes ne tiennent
       pas dans une demi-largeur sans être tronquées.
       ══════════════════════════════════════════════════════════════════ -->
  <section class="card span-all">
    <h2><span class="rule" style="background:var(--s2)"></span>Détail mensuel</h2>
    <p class="hint">Chiffre d'affaires par mois et par année, en milliers de dinars.</p>
    <div class="tbl-wrap" style="margin-top:12px">
      <table class="tbl">
        <caption class="sr-only">CA mensuel par année, en milliers de dinars</caption>
        <thead>
          <tr>
            <th scope="col">Année</th>
            <?php foreach (MOIS as $m): ?><th scope="col" class="num"><?= e($m) ?></th><?php endforeach; ?>
            <th scope="col" class="num">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (VENTES_MENSUEL as $annee => $vals): ?>
          <tr>
            <th scope="row">
              <span class="swatch" style="background:<?= e(VENTES_ANNEES_COULEURS[$annee]) ?>"></span><?= e((string) $annee) ?>
            </th>
            <?php foreach ($vals as $v): ?><td class="num"><?= money((float) $v, 0) ?></td><?php endforeach; ?>
            <td class="num" style="font-weight:680"><?= money((float) array_sum($vals), 0) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="foot-note" style="margin-top:8px">Valeurs en milliers de dinars (K DT).</p>
    </div>
    </section>

  <!-- ══════════════════════════════════════════════════════════════════
       Série journalière
       ══════════════════════════════════════════════════════════════════ -->
  <section class="card span-all">
    <h2><span class="rule" style="background:var(--s7)"></span>CA journalier — février 2022 à décembre 2024</h2>
    <p class="hint">
      <?= count($journalier) ?> jours d'activité. L'oscillation régulière correspond au
      cycle hebdomadaire : creux du dimanche au mardi, pic du vendredi au samedi.
    </p>
    <div class="chart tall"><canvas id="cJour" aria-label="Chiffre d'affaires journalier" role="img"></canvas></div>
    <p class="foot-note" style="margin-top:10px">
      Série reconstituée : les rapports d'origine n'en fournissent que la forme.
      Les totaux mensuels et annuels correspondent en revanche aux chiffres réels.
    </p>
  </section>

</div>

<script>
/* ── CA par trimestre ─────────────────────────────────────────────────── */
new Chart(document.getElementById('cTrim'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_keys(VENTES_TRIMESTRES), JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
      label: 'CA total',
      data: <?= json_encode(array_values(VENTES_TRIMESTRES)) ?>,
      backgroundColor: '#2a78d6',
      hoverBackgroundColor: '#1f66bd',
      borderRadius: { topLeft: 4, topRight: 4 },  // extrémité arrondie, base ancrée
      borderSkipped: 'bottom',
      maxBarThickness: 74,
    }],
  },
  options: {
    plugins: {
      tooltip: {
        callbacks: { label: (c) => ' ' + BIFmt.n0(c.parsed.y) + ' DT' },
      },
    },
    scales: { x: BIAxisX(), y: BIAxisY() },
  },
});

/* ── CA mensuel comparé ───────────────────────────────────────────────── */
(function () {
  const mensuel = <?= json_encode(VENTES_MENSUEL) ?>;
  const couleurs = { '2022': '#1baf7a', '2023': '#2a78d6', '2024': '#eb6834' };
  const annees = Object.keys(mensuel);

  new Chart(document.getElementById('cMois'), {
    type: 'line',
    data: {
      labels: <?= json_encode(MOIS, JSON_UNESCAPED_UNICODE) ?>,
      datasets: annees.map((a) => ({
        label: a,
        data: mensuel[a].map((v) => v * 1000),
        borderColor: couleurs[a],
        backgroundColor: couleurs[a],
        borderWidth: 2,
        pointRadius: 0,
        pointHoverRadius: 4.5,
        pointHoverBorderWidth: 2,
        pointHoverBorderColor: '#fff',   // anneau de surface sur le point survolé
        tension: 0.32,
      })),
    },
    options: {
      interaction: { mode: 'index', intersect: false },
      plugins: {
        tooltip: {
          callbacks: { label: (c) => ' ' + c.dataset.label + ' : ' + BIFmt.n0(c.parsed.y) + ' DT' },
        },
      },
      scales: { x: BIAxisX(), y: BIAxisY() },
    },
  });

  BILegend('lMois', annees.map((a) => ({ label: a, color: couleurs[a] })));
})();

/* ── CA journalier ────────────────────────────────────────────────────── */
(function () {
  const pts = <?= json_encode($journalier) ?>;

  new Chart(document.getElementById('cJour'), {
    type: 'line',
    data: {
      labels: pts.map((p) => p.d),
      datasets: [{
        label: 'CA du jour',
        data: pts.map((p) => p.v),
        borderColor: '#4a3aa7',
        backgroundColor: 'rgba(74,58,167,.10)',
        borderWidth: 1,
        pointRadius: 0,
        pointHoverRadius: 4,
        pointHoverBorderWidth: 2,
        pointHoverBorderColor: '#fff',
        fill: true,
        tension: 0.1,
      }],
    },
    options: {
      interaction: { mode: 'index', intersect: false },
      plugins: {
        tooltip: {
          callbacks: {
            title: (items) => new Date(items[0].label)
              .toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
            label: (c) => ' ' + BIFmt.n2(c.parsed.y) + ' DT',
          },
        },
      },
      scales: {
        x: BIAxisX({
          ticks: {
            color: '#7a8699', padding: 6, maxRotation: 0,
            autoSkip: true, maxTicksLimit: 11,
            // Chart.js choisit les graduations ; on se contente de les
            // mettre en forme. Filtrer sur les débuts de trimestre laissait
            // l'axe entièrement vide, aucune graduation retenue n'y tombant.
            callback(value) {
              return new Date(this.getLabelForValue(value))
                .toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' });
            },
          },
        }),
        y: BIAxisY(),
      },
    },
  });
})();
</script>

<?php layout_end(); ?>
