<?php
/**
 * bi_paiements.php — Réplication en code du rapport « Analyse des paiements »
 *
 * Reproduit la page Power BI d'origine : indicateurs d'encaissement,
 * répartition par mode de paiement, jauge de recouvrement et écarts.
 *
 * Chiffres relevés sur le rapport ; les colonnes se totalisent exactement
 * aux totaux d'origine (1 376 531,00 DT et 473 483 transactions).
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/data/bi_data.php';
require_once __DIR__ . '/partials/layout.php';

require_role(['admin', 'commercial']);

$modes     = PAIEMENTS;
$totMont   = col_sum($modes, 'montant');
$totNb     = col_sum($modes, 'nb');
$totManque = col_sum($modes, 'manquant');
$hex       = serie_hex();

// Jauge : proportion de l'aiguille sur l'arc, bornée pour rester dans le cadran.
$gaugeFrac = min(1.0, max(0.0, TAUX_RECOUVREMENT / TAUX_RECOUVREMENT_MAX));

layout_start(
    'Analyse des paiements',
    'Rapport Power BI reconstitué en code — encaissements et recouvrement',
    'bi_paiements'
);
?>

<!-- ════════════════════════════════════════════════════════════════════
     Bandeau d'indicateurs
     ════════════════════════════════════════════════════════════════════ -->
<section class="kpis" aria-label="Indicateurs d'encaissement">
  <?php foreach (PAIEMENTS_KPI as $k): ?>
    <article class="kpi" style="--accent:<?= e($k['accent']) ?>">
      <div class="lbl"><?= e($k['lbl']) ?></div>
      <div class="val">
        <?= e(kpi_value($k)) ?><?php if ($k['unit'] !== ''): ?><span class="u"><?= e($k['unit']) ?></span><?php endif; ?>
      </div>
      <div class="note"><?= e($k['note']) ?></div>
    </article>
  <?php endforeach; ?>
</section>

<div class="grid g-2">

  <!-- ══════════════════════════════════════════════════════════════════
       Répartition par mode de paiement
       ══════════════════════════════════════════════════════════════════ -->
  <section class="card">
    <h2><span class="rule"></span>Répartition par mode de paiement</h2>
    <p class="hint">
      L'espèce représente <?= money($modes[0]['montant'] / $totMont * 100, 2) ?> % des encaissements :
      la dépendance au numéraire est le fait marquant du rapport.
    </p>

    <div class="tbl-wrap">
      <table class="tbl">
        <caption class="sr-only">Montants et volumes par mode de paiement</caption>
        <thead>
          <tr>
            <th scope="col">Mode</th>
            <th scope="col" class="num">Montant (DT)</th>
            <th scope="col" class="num">Transactions</th>
            <th scope="col" class="num">Part montant</th>
            <th scope="col" class="num">Panier moy.</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($modes as $i => $m): ?>
          <tr>
            <th scope="row" style="font-weight:600">
              <span class="swatch" style="background:<?= e($hex[$i % 8]) ?>"></span><?= e($m['code']) ?>
              <div style="font-size:.715rem;color:var(--ink-muted);font-weight:400;
                          margin-left:17px"><?= e($m['lib']) ?></div>
            </th>
            <td class="num"><?= money($m['montant'], 2) ?></td>
            <td class="num"><?= money((float) $m['nb'], 0) ?></td>
            <td class="num"><?= money($m['montant'] / $totMont * 100, 2) ?> %</td>
            <td class="num"><?= money($m['montant'] / max(1, $m['nb']), 2) ?></td>
          </tr>
          <?php endforeach; ?>
          <tr class="total">
            <td>Total</td>
            <td class="num"><?= money($totMont, 2) ?></td>
            <td class="num"><?= money($totNb, 0) ?></td>
            <td class="num">100,00 %</td>
            <td class="num"><?= money($totMont / $totNb, 2) ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="chart short" style="margin-top:16px">
      <canvas id="cModes" aria-label="Montant encaissé par mode de paiement" role="img"></canvas>
    </div>
  </section>

  <!-- ══════════════════════════════════════════════════════════════════
       Recouvrement + écarts
       ══════════════════════════════════════════════════════════════════ -->
  <section class="card">
    <h2><span class="rule" style="background:var(--s3)"></span>Taux de recouvrement</h2>
    <p class="hint">Rapport entre le montant reçu et le montant dû.</p>

    <!-- Jauge demi-circulaire en SVG : pas de dépendance graphique,
         et elle reste nette à toute taille d'écran. -->
    <div class="gauge">
      <svg viewBox="0 0 200 116" role="img"
           aria-label="Taux de recouvrement : <?= money(TAUX_RECOUVREMENT, 2) ?> sur une échelle allant jusqu'à <?= money(TAUX_RECOUVREMENT_MAX, 2) ?>">
        <?php
        // Arc de fond et arc de valeur, dessinés par un trait circulaire
        // dont on module la longueur visible.
        $r  = 78;
        $cx = 100;
        $cy = 100;
        $len = M_PI * $r;           // longueur d'un demi-cercle
        ?>
        <path d="M <?= $cx - $r ?> <?= $cy ?> A <?= $r ?> <?= $r ?> 0 0 1 <?= $cx + $r ?> <?= $cy ?>"
              fill="none" stroke="#e8ecf3" stroke-width="17" stroke-linecap="round"/>
        <path d="M <?= $cx - $r ?> <?= $cy ?> A <?= $r ?> <?= $r ?> 0 0 1 <?= $cx + $r ?> <?= $cy ?>"
              fill="none" stroke="#1baf7a" stroke-width="17" stroke-linecap="round"
              stroke-dasharray="<?= round($len * $gaugeFrac, 2) ?> <?= round($len, 2) ?>"/>
        <text x="<?= $cx - $r ?>" y="114" font-size="10" fill="#7a8699" text-anchor="middle">0,00</text>
        <text x="<?= $cx + $r ?>" y="114" font-size="10" fill="#7a8699" text-anchor="middle"><?= money(TAUX_RECOUVREMENT_MAX, 2) ?></text>
      </svg>
      <div class="g-val"><?= money(TAUX_RECOUVREMENT, 2) ?></div>
      <div class="g-lbl">Montant reçu / montant dû</div>
    </div>

    <div class="alert alert-info" style="margin-top:18px">
      <span class="ico" aria-hidden="true">✓</span>
      <span>
        Recouvrement complet : <strong><?= money($totMont, 2) ?> DT</strong> encaissés
        pour un montant dû identique. Seules
        <strong><?= money($totManque, 2) ?> DT</strong> restent manquantes, sur le mode
        <strong>TR</strong> — soit <?= money($totManque / $totMont * 100, 6) ?> % du total.
      </span>
    </div>

    <h2 style="margin-top:22px"><span class="rule" style="background:var(--s2)"></span>Écarts de caisse</h2>
    <p class="hint">Transactions dont le montant reçu diffère du montant dû.</p>

    <div class="tbl-wrap">
      <table class="tbl">
        <caption class="sr-only">Écarts de caisse constatés</caption>
        <thead>
          <tr><th scope="col">Nature</th><th scope="col" class="num">Nb</th><th scope="col" class="num">Part</th></tr>
        </thead>
        <tbody>
          <tr>
            <th scope="row" style="font-weight:500">
              <span class="badge badge-amber">Trop payées</span>
            </th>
            <td class="num">178</td>
            <td class="num"><?= money(178 / $totNb * 100, 4) ?> %</td>
          </tr>
          <tr>
            <th scope="row" style="font-weight:500">
              <span class="badge badge-violet">Sous-payées</span>
            </th>
            <td class="num">2</td>
            <td class="num"><?= money(2 / $totNb * 100, 6) ?> %</td>
          </tr>
          <tr>
            <th scope="row" style="font-weight:500">
              <span class="badge badge-green">Conformes</span>
            </th>
            <td class="num"><?= money($totNb - 180, 0) ?></td>
            <td class="num"><?= money(($totNb - 180) / $totNb * 100, 4) ?> %</td>
          </tr>
        </tbody>
      </table>
    </div>

    <p class="foot-note" style="margin-top:12px">
      180 anomalies sur <?= money($totNb, 0) ?> transactions : le processus
      d'encaissement est fiable à <?= money(($totNb - 180) / $totNb * 100, 2) ?> %.
    </p>
  </section>

</div>

<script>
/* ── Montant par mode de paiement ─────────────────────────────────────── */
(function () {
  const modes = <?= json_encode(array_map(static fn ($m) => [
      'code'    => $m['code'],
      'lib'     => $m['lib'],
      'montant' => round($m['montant'], 2),
  ], $modes), JSON_UNESCAPED_UNICODE) ?>;
  const couleurs = <?= json_encode($hex) ?>;

  new Chart(document.getElementById('cModes'), {
    type: 'bar',
    data: {
      labels: modes.map((m) => m.code),
      datasets: [{
        label: 'Montant encaissé',
        data: modes.map((m) => m.montant),
        backgroundColor: couleurs.slice(0, modes.length),
        borderRadius: { topRight: 4, bottomRight: 4 },
        borderSkipped: 'left',
        maxBarThickness: 26,
      }],
    },
    options: {
      indexAxis: 'y',   // barres horizontales : les libellés restent droits
      plugins: {
        tooltip: {
          callbacks: {
            title: (items) => modes[items[0].dataIndex].lib,
            label: (c) => ' ' + BIFmt.n2(c.parsed.x) + ' DT',
          },
        },
      },
      scales: {
        x: BIAxisY(),                              // l'axe des valeurs est en X
        y: BIAxisX({ border: { display: false } }), // l'axe des catégories est en Y
      },
    },
  });
})();
</script>

<?php layout_end(); ?>
