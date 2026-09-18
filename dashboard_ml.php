<?php
/**
 * dashboard_ml.php — Prédiction et data mining
 *
 * Reprend les quatre blocs du tableau de bord d'origine :
 *   1. Prévision du chiffre d'affaires (série temporelle)
 *   2. Prédiction de CA par produit (formulaire)
 *   3. Segmentation des produits (K-Means)
 *   4. Règles d'association (FP-Growth)
 *
 * La différence : plus aucun appel à Python. Les résultats des modèles
 * sont servis depuis data/ml_data.php. Voir l'en-tête de ce fichier pour
 * le détail de ce qui est réel et de ce qui est reconstitué.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/data/ml_data.php';
require_once __DIR__ . '/partials/layout.php';

require_role(['admin', 'commercial']);

// ── Formulaire de prédiction ────────────────────────────────────────────
$saisie = [
    'id_produit'   => (int) ($_POST['id_produit']   ?? 1),
    'mois'         => (int) ($_POST['mois']         ?? (int) date('n')),
    'annee'        => (int) ($_POST['annee']        ?? (int) date('Y')),
    'nb_commandes' => (int) ($_POST['nb_commandes'] ?? 500),
];

$soumis    = isset($_POST['predire']);
$resultat  = null;
$erreurCsrf = false;

if ($soumis) {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $erreurCsrf = true;
    } else {
        $resultat = predire_ca(
            $saisie['id_produit'],
            $saisie['mois'],
            $saisie['annee'],
            $saisie['nb_commandes']
        );
    }
}

$prev     = previsions();
$maxLift  = max(array_column(REGLES, 'lift'));
$caGlobal = array_sum(array_column(CLUSTERS, 'ca_total'));

layout_start(
    'Prédiction & Data Mining',
    'Prévision de CA, segmentation produits et analyse du panier',
    'ml'
);
?>

<div class="alert alert-info">
  <span class="ico" aria-hidden="true">ℹ</span>
  <span>
    <strong>Segmentation et règles d'association : résultats réels</strong>, extraits des
    fichiers <code class="inline">models/clusters_produits.csv</code> et
    <code class="inline">models/association_rules.csv</code> produits par les notebooks du projet.
    <strong>Prévision et prédiction : reconstituées</strong> — les modèles
    <code class="inline">.pkl</code> exigent Python côté serveur, ce qu'aucun hébergement PHP
    standard ne fournit. La marche à suivre pour les rebrancher est décrite dans le README.
  </span>
</div>

<div class="grid g-2">

  <!-- ══════════════════════════════════════════════════════════════════
       BLOC 1 — Prévision du chiffre d'affaires
       ══════════════════════════════════════════════════════════════════ -->
  <section class="card span-all">
    <h2><span class="rule"></span>Prévision du chiffre d'affaires
      <span class="badge badge-slate" style="margin-left:4px">Série temporelle</span>
    </h2>
    <p class="hint">
      26 semaines observées, puis 12 semaines de projection. La bande grise
      est l'intervalle de confiance : il s'élargit avec l'horizon, comme
      toute prévision honnête.
    </p>

    <div class="chart tall"><canvas id="cPrev" aria-label="Prévision hebdomadaire du chiffre d'affaires" role="img"></canvas></div>
    <div class="legend" id="lPrev"></div>

    <div class="tbl-wrap" style="margin-top:16px">
      <table class="tbl">
        <caption class="sr-only">Détail des douze semaines de prévision</caption>
        <thead>
          <tr>
            <th scope="col">Semaine du</th>
            <th scope="col" class="num">CA prévu (DT)</th>
            <th scope="col" class="num">Borne basse</th>
            <th scope="col" class="num">Borne haute</th>
            <th scope="col" class="num">Amplitude</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_filter($prev, static fn ($r) => $r['futur']) as $r): ?>
          <tr>
            <th scope="row" style="font-weight:500">
              <?= e((new DateTimeImmutable($r['ds']))->format('d/m/Y')) ?>
            </th>
            <td class="num" style="font-weight:640"><?= money($r['yhat'], 2) ?></td>
            <td class="num" style="color:var(--ink-muted)"><?= money($r['bas'], 2) ?></td>
            <td class="num" style="color:var(--ink-muted)"><?= money($r['haut'], 2) ?></td>
            <td class="num">± <?= money(($r['haut'] - $r['bas']) / 2 / $r['yhat'] * 100, 1) ?> %</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- ══════════════════════════════════════════════════════════════════
       BLOC 2 — Prédiction de CA par produit
       ══════════════════════════════════════════════════════════════════ -->
  <section class="card">
    <h2><span class="rule" style="background:var(--s3)"></span>Prédiction de CA par produit</h2>
    <p class="hint">
      Estime le chiffre d'affaires d'une référence sur un mois donné, à
      partir de son panier moyen observé et de la saisonnalité.
    </p>

    <?php if ($erreurCsrf): ?>
      <div class="alert alert-err" style="margin-bottom:14px">
        <span class="ico" aria-hidden="true">⚠</span>
        <span>Session expirée. Rechargez la page et renvoyez le formulaire.</span>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="form-row">
        <div class="field" style="flex:2 1 195px">
          <label for="f_prod">Produit</label>
          <select name="id_produit" id="f_prod">
            <?php foreach (CATALOGUE as $p): ?>
              <option value="<?= $p['id'] ?>" <?= $p['id'] === $saisie['id_produit'] ? 'selected' : '' ?>>
                <?= e($p['nom']) ?> — <?= money($p['prix'], 2) ?> DT
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="f_mois">Mois</label>
          <select name="mois" id="f_mois">
            <?php foreach (MOIS as $i => $m): ?>
              <option value="<?= $i + 1 ?>" <?= ($i + 1) === $saisie['mois'] ? 'selected' : '' ?>><?= e($m) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="f_annee">Année</label>
          <input type="number" name="annee" id="f_annee" min="2022" max="2030"
                 value="<?= $saisie['annee'] ?>" required>
        </div>

        <div class="field">
          <label for="f_cmd">Nb commandes</label>
          <input type="number" name="nb_commandes" id="f_cmd" min="0" step="1"
                 value="<?= $saisie['nb_commandes'] ?>" required>
        </div>

        <button class="btn" type="submit" name="predire">Prédire</button>
      </div>
    </form>

    <?php if ($resultat !== null && ($resultat['ok'] ?? false)): ?>
      <?php $d = $resultat['detail']; ?>
      <div class="kpi" style="--accent:var(--s3);margin-top:18px">
        <div class="lbl">CA prédit</div>
        <div class="val"><?= money($resultat['ca'], 2) ?><span class="u">DT</span></div>
        <div class="note">
          <?= e($d['produit']) ?> &middot; <?= e(MOIS[$saisie['mois'] - 1]) ?> <?= $saisie['annee'] ?>
          &middot; <?= money((float) $d['nb_commandes'], 0) ?> commandes
        </div>
      </div>

      <!-- Le calcul est montré : une prédiction que l'on ne peut pas
           vérifier ne sert à rien en pilotage. -->
      <div class="tbl-wrap" style="margin-top:14px">
        <table class="tbl">
          <caption class="sr-only">Décomposition du calcul</caption>
          <thead><tr><th scope="col">Facteur</th><th scope="col" class="num">Valeur</th><th scope="col">Origine</th></tr></thead>
          <tbody>
            <tr>
              <th scope="row" style="font-weight:500">Nombre de commandes</th>
              <td class="num"><?= money((float) $d['nb_commandes'], 0) ?></td>
              <td style="color:var(--ink-muted)">Saisi</td>
            </tr>
            <tr>
              <th scope="row" style="font-weight:500">Panier moyen du produit</th>
              <td class="num"><?= money($d['panier'], 4) ?> DT</td>
              <td style="color:var(--ink-muted)">CA observé / commandes observées</td>
            </tr>
            <tr>
              <th scope="row" style="font-weight:500">Coefficient saisonnier</th>
              <td class="num">× <?= money($d['coef_mois'], 4) ?></td>
              <td style="color:var(--ink-muted)"><?= e(MOIS[$saisie['mois'] - 1]) ?> vs mois moyen</td>
            </tr>
            <tr>
              <th scope="row" style="font-weight:500">Coefficient de tendance</th>
              <td class="num">× <?= money($d['coef_annee'], 4) ?></td>
              <td style="color:var(--ink-muted)"><?= $saisie['annee'] ?>, base 2023 = 1</td>
            </tr>
            <tr class="total">
              <td>CA prédit</td>
              <td class="num"><?= money($resultat['ca'], 2) ?> DT</td>
              <td></td>
            </tr>
          </tbody>
        </table>
      </div>
    <?php elseif ($resultat !== null): ?>
      <div class="alert alert-err" style="margin-top:16px">
        <span class="ico" aria-hidden="true">⚠</span>
        <span><?= e($resultat['error'] ?? 'Erreur inconnue.') ?></span>
      </div>
    <?php else: ?>
      <p class="foot-note" style="margin-top:14px">
        Choisissez un produit et une période, puis lancez la prédiction.
        Le détail du calcul s'affiche sous le résultat.
      </p>
    <?php endif; ?>
  </section>

  <!-- ══════════════════════════════════════════════════════════════════
       BLOC 3 — Segmentation K-Means
       ══════════════════════════════════════════════════════════════════ -->
  <section class="card">
    <h2><span class="rule" style="background:var(--s4)"></span>Segmentation des produits
      <span class="badge badge-slate" style="margin-left:4px">K-Means · k=4</span>
    </h2>
    <p class="hint">
      <?= CLUSTERS_NB_PRODUITS ?> références réparties en quatre groupes selon le prix,
      le volume et la fréquence d'achat.
    </p>

    <div class="chart short"><canvas id="cClusters" aria-label="CA moyen par segment de produits" role="img"></canvas></div>

    <div class="tbl-wrap" style="margin-top:14px">
      <table class="tbl">
        <caption class="sr-only">Caractéristiques des quatre segments</caption>
        <thead>
          <tr>
            <th scope="col">Segment</th>
            <th scope="col" class="num">Réfs</th>
            <th scope="col" class="num">CA moyen</th>
            <th scope="col" class="num">Prix moyen</th>
            <th scope="col" class="num">Part du CA</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (CLUSTERS as $c): ?>
          <tr>
            <th scope="row" style="font-weight:500">
              <span class="swatch" style="background:<?= e($c['couleur']) ?>"></span><?= e($c['nom']) ?>
            </th>
            <td class="num"><?= $c['nb'] ?></td>
            <td class="num"><?= money($c['ca_moyen'], 0) ?></td>
            <td class="num"><?= money($c['prix_moyen'], 2) ?></td>
            <td class="num"><?= money($c['ca_total'] / $caGlobal * 100, 1) ?> %</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="display:grid;gap:9px;margin-top:16px">
      <?php foreach (CLUSTERS as $c): ?>
        <div style="border-left:3px solid <?= e($c['couleur']) ?>;padding:2px 0 2px 11px">
          <div style="font-size:.815rem;font-weight:640"><?= e($c['nom']) ?></div>
          <div style="font-size:.755rem;color:var(--ink-muted);line-height:1.45">
            <?= e($c['desc']) ?><br>
            <span style="color:var(--ink-2)">Ex. : <?= e(implode(', ', $c['exemples'])) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="alert alert-warn" style="margin-top:16px">
      <span class="ico" aria-hidden="true">⚠</span>
      <span>
        <strong>Libellés corrigés.</strong> Le code d'origine étiquetait le cluster 2
        « Produit Moyen » et le cluster 3 « Premium ». Les chiffres disent l'inverse :
        c'est le cluster 2 qui réunit les produits chers (6,95 DT en moyenne) à faible
        rotation. Les libellés ont été réalignés sur les données.
      </span>
    </div>
  </section>

  <!-- ══════════════════════════════════════════════════════════════════
       BLOC 4 — Règles d'association
       ══════════════════════════════════════════════════════════════════ -->
  <section class="card span-all">
    <h2><span class="rule" style="background:var(--s2)"></span>Analyse du panier
      <span class="badge badge-slate" style="margin-left:4px">FP-Growth</span>
    </h2>
    <p class="hint">
      Couples de produits achetés ensemble plus souvent que le hasard ne le
      prévoit. Un <em>lift</em> de 3,13 signifie que l'association est trois
      fois plus fréquente qu'une rencontre fortuite.
    </p>

    <div class="tbl-wrap">
      <table class="tbl">
        <caption class="sr-only">Règles d'association extraites des tickets de caisse</caption>
        <thead>
          <tr>
            <th scope="col">Si le client achète…</th>
            <th scope="col">… il prend aussi</th>
            <th scope="col">Lift</th>
            <th scope="col" class="num">Confiance</th>
            <th scope="col" class="num">Support</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (REGLES as $r): ?>
          <tr>
            <th scope="row" style="font-weight:500"><?= e($r['si']) ?></th>
            <td><strong><?= e($r['alors']) ?></strong></td>
            <td style="min-width:155px">
              <div style="display:flex;align-items:center;gap:9px">
                <span class="badge <?= $r['lift'] >= 2 ? 'badge-green' : ($r['lift'] >= 1.5 ? 'badge-amber' : 'badge-slate') ?>">
                  <?= money($r['lift'], 3) ?>
                </span>
                <span style="flex:1;background:var(--surface-sunken);border-radius:3px;height:6px;overflow:hidden">
                  <span style="display:block;height:100%;border-radius:3px;background:var(--s2);
                               width:<?= round($r['lift'] / $maxLift * 100, 1) ?>%"></span>
                </span>
              </div>
            </td>
            <td class="num"><?= money($r['confiance'] * 100, 1) ?> %</td>
            <td class="num"><?= money($r['support'] * 100, 3) ?> %</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="alert alert-info" style="margin-top:16px">
      <span class="ico" aria-hidden="true">→</span>
      <span>
        <strong>Lecture opérationnelle.</strong> Les trois règles les plus fortes partent
        toutes de la viennoiserie ou des gobelets vers une boisson chaude
        (Direct, Cappucin, Express) : c'est le réflexe petit-déjeuner.
        Rapprocher physiquement ces références, ou les proposer en formule,
        est l'action la plus directe que suggère cette analyse.
      </span>
    </div>
  </section>

</div>

<script>
/* ── Prévision hebdomadaire ───────────────────────────────────────────── */
(function () {
  const rows = <?= json_encode($prev) ?>;
  const labels = rows.map((r) => r.ds);
  const coupure = rows.findIndex((r) => r.futur);

  // Deux séries distinctes, avec un point de recouvrement pour que la
  // courbe ne se casse pas visuellement à la jonction.
  const observe = rows.map((r, i) => (i <= coupure - 1 ? r.yhat : null));
  const projete = rows.map((r, i) => (i >= coupure - 1 ? r.yhat : null));
  const haut    = rows.map((r, i) => (i >= coupure - 1 ? r.haut : null));
  const bas     = rows.map((r, i) => (i >= coupure - 1 ? r.bas  : null));

  new Chart(document.getElementById('cPrev'), {
    type: 'line',
    data: {
      labels,
      datasets: [
        // Bande de confiance : tracée en premier pour rester sous les courbes.
        { label: 'Intervalle', data: haut, borderColor: 'transparent',
          backgroundColor: 'rgba(122,134,153,.14)', pointRadius: 0,
          fill: '+1', tension: .3, order: 3 },
        { label: '', data: bas, borderColor: 'transparent',
          backgroundColor: 'transparent', pointRadius: 0, fill: false,
          tension: .3, order: 3 },

        { label: 'CA observé', data: observe, borderColor: '#2a78d6',
          backgroundColor: '#2a78d6', borderWidth: 2, pointRadius: 0,
          pointHoverRadius: 4.5, pointHoverBorderWidth: 2,
          pointHoverBorderColor: '#fff', tension: .3, order: 1 },

        { label: 'Prévision', data: projete, borderColor: '#eb6834',
          backgroundColor: '#eb6834', borderWidth: 2, borderDash: [5, 4],
          pointRadius: 0, pointHoverRadius: 4.5, pointHoverBorderWidth: 2,
          pointHoverBorderColor: '#fff', tension: .3, order: 2 },
      ],
    },
    options: {
      interaction: { mode: 'index', intersect: false },
      plugins: {
        tooltip: {
          filter: (item) => item.dataset.label !== '' && item.parsed.y !== null,
          callbacks: {
            title: (items) => 'Semaine du '
              + new Date(items[0].label).toLocaleDateString('fr-FR',
                  { day: 'numeric', month: 'long', year: 'numeric' }),
            label: (c) => ' ' + c.dataset.label + ' : ' + BIFmt.n2(c.parsed.y) + ' DT',
          },
        },
      },
      scales: {
        x: BIAxisX({
          ticks: {
            color: '#7a8699', padding: 6, maxRotation: 0, autoSkip: true, maxTicksLimit: 10,
            callback(v) {
              return new Date(this.getLabelForValue(v))
                .toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
            },
          },
        }),
        y: BIAxisY(),
      },
    },
  });

  BILegend('lPrev', [
    { label: 'CA observé (26 semaines)', color: '#2a78d6' },
    { label: 'Prévision (12 semaines)',  color: '#eb6834' },
    { label: 'Intervalle de confiance',  color: 'rgba(122,134,153,.45)' },
  ]);
})();

/* ── CA moyen par segment ─────────────────────────────────────────────── */
(function () {
  const cl = <?= json_encode(array_map(static fn ($c) => [
      'nom'      => $c['nom'],
      'ca_moyen' => round($c['ca_moyen'], 2),
      'nb'       => $c['nb'],
      'couleur'  => $c['couleur'],
  ], CLUSTERS), JSON_UNESCAPED_UNICODE) ?>;

  new Chart(document.getElementById('cClusters'), {
    type: 'bar',
    data: {
      // Libellés coupés en deux lignes : « Premium faible volume » sur une
      // seule ligne chevauchait ses voisins sous la barre.
      labels: cl.map((c) => {
        const mots = c.nom.split(' ');
        return mots.length > 1
          ? [mots[0], mots.slice(1).join(' ')]
          : c.nom;
      }),
      datasets: [{
        label: 'CA moyen par référence',
        data: cl.map((c) => c.ca_moyen),
        backgroundColor: cl.map((c) => c.couleur),
        borderRadius: { topLeft: 4, topRight: 4 },
        borderSkipped: 'bottom',
        maxBarThickness: 58,
      }],
    },
    options: {
      plugins: {
        tooltip: {
          callbacks: {
            label: (c) => ' ' + BIFmt.n0(c.parsed.y) + ' DT en moyenne',
            afterLabel: (c) => '  sur ' + cl[c.dataIndex].nb + ' références',
          },
        },
      },
      scales: {
        x: BIAxisX({ ticks: { color: '#7a8699', padding: 6, maxRotation: 0, autoSkip: false, font: { size: 10 } } }),
        y: BIAxisY(),
      },
    },
  });
})();
</script>

<?php layout_end(); ?>
