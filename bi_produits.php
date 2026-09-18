<?php
/**
 * bi_produits.php — Réplication en code du rapport « Performance produits »
 *
 * Reproduit la page Power BI d'origine : tableau détaillé, carte du
 * meilleur produit, anneau de répartition du CA, barres du nombre de
 * commandes et carte proportionnelle (treemap).
 *
 * Les chiffres du tableau sont ceux du rapport : les colonnes se
 * totalisent exactement aux totaux d'origine (655 138,00 unités et
 * 374 907,39 DT).
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/data/bi_data.php';
require_once __DIR__ . '/partials/layout.php';

require_role(['admin', 'commercial', 'employe']);

// ── Préparation des données ─────────────────────────────────────────────
$produits = PRODUITS;                       // déjà triés par CA décroissant
$totalCa  = col_sum($produits, 'ca');
$totalQte = col_sum($produits, 'qte');
$totalCmd = col_sum($produits, 'commandes');
$best     = $produits[0];
$hex      = serie_hex();

// Le tableau compte 10 produits pour 8 couleurs de série : les deux plus
// petits sont regroupés sous « Autres » dans l'anneau plutôt que de
// recycler une couleur, ce qui créerait deux produits de même teinte.
$anneau = array_slice($produits, 0, 7);
$reste  = array_slice($produits, 7);
$anneau[] = ['nom' => 'Autres (' . count($reste) . ')', 'ca' => col_sum($reste, 'ca')];

/**
 * Carte proportionnelle par l'algorithme « squarify » (Bruls, Huizing &
 * van Wijk, 2000).
 *
 * Le principe : on empile les pavés en bandes successives, et on ferme
 * une bande dès que l'ajout d'un pavé de plus dégraderait le rapport
 * largeur/hauteur du pire pavé de la bande. On obtient des rectangles
 * proches du carré, donc lisibles — contrairement à un simple découpage
 * en colonnes, qui produit des bandes très étroites pour les petites
 * valeurs et tronque leurs libellés.
 *
 * Retourne des positions en pourcentage, directement exploitables en CSS.
 *
 * @return array<int, array{x:float,y:float,w:float,h:float,row:array}>
 */
function squarify(array $rows, float $x, float $y, float $w, float $h): array
{
    $out = [];

    // Mise à l'échelle : la somme des valeurs doit égaler l'aire disponible.
    $somme = array_sum(array_column($rows, 'ca'));
    if ($somme <= 0 || $rows === []) {
        return $out;
    }
    $facteur = ($w * $h) / $somme;
    foreach ($rows as $i => $r) {
        $rows[$i]['aire'] = $r['ca'] * $facteur;
    }

    /** Pire rapport d'aspect d'une bande de largeur $cote. */
    $pire = static function (array $bande, float $cote): float {
        if ($bande === [] || $cote <= 0) {
            return INF;
        }
        $s   = array_sum(array_column($bande, 'aire'));
        $min = min(array_column($bande, 'aire'));
        $max = max(array_column($bande, 'aire'));
        if ($s <= 0 || $min <= 0) {
            return INF;
        }
        return max(($cote ** 2 * $max) / ($s ** 2), ($s ** 2) / ($cote ** 2 * $min));
    };

    $reste = $rows;

    while ($reste !== []) {
        // La bande se pose toujours le long du côté le plus court.
        $cote  = min($w, $h);
        $bande = [];

        while ($reste !== []) {
            $essai = array_merge($bande, [$reste[0]]);
            if ($bande !== [] && $pire($essai, $cote) > $pire($bande, $cote)) {
                break; // ajouter ce pavé abîmerait la bande : on la ferme
            }
            $bande = $essai;
            array_shift($reste);
        }

        // Placement des pavés de la bande.
        $aireBande = array_sum(array_column($bande, 'aire'));
        $epaisseur = $cote > 0 ? $aireBande / $cote : 0;

        if ($w >= $h) {
            // Bande verticale, à gauche de la zone restante.
            $cy = $y;
            foreach ($bande as $c) {
                $ch = $aireBande > 0 ? $c['aire'] / $epaisseur : 0;
                $out[] = ['x' => $x, 'y' => $cy, 'w' => $epaisseur, 'h' => $ch, 'row' => $c];
                $cy += $ch;
            }
            $x += $epaisseur;
            $w -= $epaisseur;
        } else {
            // Bande horizontale, en haut de la zone restante.
            $cx = $x;
            foreach ($bande as $c) {
                $cw = $aireBande > 0 ? $c['aire'] / $epaisseur : 0;
                $out[] = ['x' => $cx, 'y' => $y, 'w' => $cw, 'h' => $epaisseur, 'row' => $c];
                $cx += $cw;
            }
            $y += $epaisseur;
            $h -= $epaisseur;
        }
    }

    return $out;
}

/**
 * Couleur d'un produit selon son rang.
 *
 * La palette compte 8 teintes et le tableau 10 produits. Plutôt que de
 * repartir au début — deux produits porteraient alors la même couleur —
 * les trois derniers reçoivent la teinte du regroupement « Autres » de
 * l'anneau. Une teinte désigne ainsi toujours la même chose sur les trois
 * visuels de la page.
 */
function couleur_produit(int $rang): string
{
    $hex = serie_hex();
    return $rang < 7 ? $hex[$rang] : $hex[7];
}

// Les produits portent leur rang, pour conserver la couleur du tableau.
$pourTree = [];
foreach ($produits as $i => $p) {
    $pourTree[] = $p + ['idx' => $i];
}
$pavés = squarify($pourTree, 0, 0, 100, 100);

layout_start(
    'Performance produits',
    'Rapport Power BI reconstitué en code — top 10 des références',
    'bi_produits'
);
?>

<!-- Segments repris du rapport d'origine (présentation seule) -->
<div class="filters">
  <span class="fl">Client</span>
  <span class="chips">
    <?php foreach (PRODUITS_FILTRES['nom_client'] as $c): ?>
      <span class="chip"><?= e($c) ?></span>
    <?php endforeach; ?>
  </span>
  <span class="fl">Type produit</span>
  <span class="chips">
    <?php foreach (PRODUITS_FILTRES['type_produit'] as $t): ?>
      <span class="chip <?= $t === 'consu' ? 'on' : '' ?>"><?= e($t) ?></span>
    <?php endforeach; ?>
  </span>
  <span class="fl">Période</span>
  <span class="chips"><span class="chip on"><?= e(PRODUITS_FILTRES['periode']) ?></span></span>
</div>

<div class="grid g-2">

  <!-- ══════════════════════════════════════════════════════════════════
       Meilleur produit + tableau détaillé
       ══════════════════════════════════════════════════════════════════ -->
  <section class="card">
    <h2><span class="rule"></span>Détail par produit</h2>
    <p class="hint">Quantités, chiffre d'affaires et part de CA — relevé du rapport.</p>

    <div class="kpi" style="--accent:var(--s1);margin-bottom:16px">
      <div class="lbl">Meilleur produit</div>
      <div class="val" style="font-size:1.5rem"><?= e($best['nom']) ?></div>
      <div class="note">
        <?= money($best['ca'], 2) ?> DT &middot;
        <?= money($best['ca'] / $totalCa * 100, 2) ?> % du CA du top 10
      </div>
    </div>

    <div class="tbl-wrap">
      <table class="tbl">
        <caption class="sr-only">Performance détaillée des dix premiers produits</caption>
        <thead>
          <tr>
            <th scope="col">Produit</th>
            <th scope="col" class="num">Qté vendue</th>
            <th scope="col" class="num">CA total (DT)</th>
            <th scope="col" class="num">Part CA</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($produits as $i => $p): ?>
          <tr>
            <th scope="row" style="font-weight:500">
              <span class="swatch" style="background:<?= e(couleur_produit($i)) ?>"></span><?= e($p['nom']) ?>
            </th>
            <td class="num"><?= money($p['qte'], 0) ?></td>
            <td class="num"><?= money($p['ca'], 2) ?></td>
            <td class="num"><?= money($p['ca'] / $totalCa * 100, 2) ?> %</td>
          </tr>
          <?php endforeach; ?>
          <tr class="total">
            <td>Total</td>
            <td class="num"><?= money($totalQte, 0) ?></td>
            <td class="num"><?= money($totalCa, 2) ?></td>
            <td class="num">100,00 %</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <!-- ══════════════════════════════════════════════════════════════════
       Répartition du CA
       ══════════════════════════════════════════════════════════════════ -->
  <section class="card">
    <h2><span class="rule" style="background:var(--s2)"></span>Répartition du chiffre d'affaires</h2>
    <p class="hint">
      Deux références — <?= e($produits[0]['nom']) ?> et <?= e($produits[1]['nom']) ?> —
      pèsent à elles seules
      <?= money(($produits[0]['ca'] + $produits[1]['ca']) / $totalCa * 100, 1) ?> % du total.
    </p>
    <div class="chart tall"><canvas id="cAnneau" aria-label="Répartition du CA par produit" role="img"></canvas></div>
    <div class="legend" id="lAnneau"></div>
  </section>

  <!-- ══════════════════════════════════════════════════════════════════
       Nombre de commandes
       ══════════════════════════════════════════════════════════════════ -->
  <section class="card">
    <h2><span class="rule" style="background:var(--s3)"></span>Nombre de commandes par produit</h2>
    <p class="hint">
      Fréquence d'achat, à distinguer du chiffre d'affaires : un produit
      très demandé peut peser peu en valeur.
    </p>

    <!-- Barres construites en HTML : toujours lisibles, y compris à
         l'impression et sans JavaScript. -->
    <div class="hbars">
      <?php
      $maxCmd = max(array_column($produits, 'commandes'));
      // Tri par nombre de commandes, indépendant du tri par CA du tableau.
      $parCmd = $produits;
      usort($parCmd, static fn ($a, $b) => $b['commandes'] <=> $a['commandes']);
      foreach ($parCmd as $p): ?>
        <div class="hbar">
          <span class="lab" title="<?= e($p['nom']) ?>"><?= e($p['nom']) ?></span>
          <span class="track">
            <span class="fill" style="width:<?= round($p['commandes'] / $maxCmd * 100, 2) ?>%"></span>
          </span>
          <span class="val"><?= money((float) $p['commandes'], 0) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <p class="foot-note" style="margin-top:12px">
      Total top 10 : <?= money($totalCmd, 0) ?> commandes.
      Source : <code class="inline">models/clusters_produits.csv</code>.
    </p>
  </section>

  <!-- ══════════════════════════════════════════════════════════════════
       Carte proportionnelle
       ══════════════════════════════════════════════════════════════════ -->
  <section class="card">
    <h2><span class="rule" style="background:var(--s4)"></span>Poids relatif des produits</h2>
    <p class="hint">Surface proportionnelle au chiffre d'affaires.</p>

    <div class="treemap">
      <?php foreach ($pavés as $p):
          $c = $p['row'];
          // Le niveau de détail dépend de la place réellement disponible :
          // un pavé étroit ne peut pas porter deux lignes de texte.
          $classe = '';
          if ($p['w'] < 12 || $p['h'] < 12)      { $classe = 'micro'; }
          elseif ($p['w'] < 22 || $p['h'] < 20) { $classe = 'tiny'; }
      ?>
        <div class="cell <?= $classe ?>"
             style="left:<?= round($p['x'], 3) ?>%;top:<?= round($p['y'], 3) ?>%;
                    width:<?= round($p['w'], 3) ?>%;height:<?= round($p['h'], 3) ?>%;
                    background:<?= e(couleur_produit((int) $c['idx'])) ?>"
             title="<?= e($c['nom']) ?> — <?= money($c['ca'], 2) ?> DT (<?= money($c['ca'] / $totalCa * 100, 2) ?> %)">
          <span class="n"><?= e($c['nom']) ?></span>
          <span class="v"><?= money($c['ca'], 0) ?> DT</span>
        </div>
      <?php endforeach; ?>
    </div>

    <p class="foot-note" style="margin-top:12px">
      Les valeurs exactes figurent dans le tableau « Détail par produit ».
    </p>
  </section>

</div>

<script>
/* ── Anneau de répartition du CA ──────────────────────────────────────── */
(function () {
  const items = <?= json_encode(array_map(
      static fn ($r) => ['nom' => $r['nom'], 'ca' => round($r['ca'], 2)],
      $anneau
  ), JSON_UNESCAPED_UNICODE) ?>;
  const couleurs = <?= json_encode($hex) ?>;
  const total = items.reduce((s, i) => s + i.ca, 0);

  new Chart(document.getElementById('cAnneau'), {
    type: 'doughnut',
    data: {
      labels: items.map((i) => i.nom),
      datasets: [{
        data: items.map((i) => i.ca),
        backgroundColor: couleurs,
        borderColor: '#ffffff',
        borderWidth: 2,          // écart de 2 px entre segments voisins
        hoverOffset: 6,
      }],
    },
    options: {
      cutout: '58%',
      plugins: {
        tooltip: {
          callbacks: {
            label: (c) => ' ' + BIFmt.n2(c.parsed) + ' DT — '
              + BIFmt.pct(c.parsed / total * 100),
          },
        },
      },
    },
  });

  BILegend('lAnneau', items.map((i, n) => ({
    label: i.nom + ' · ' + (i.ca / total * 100).toFixed(1).replace('.', ',') + ' %',
    color: couleurs[n],
  })));
})();
</script>

<?php layout_end(); ?>
