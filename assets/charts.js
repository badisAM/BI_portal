/* ==========================================================================
   charts.js — Réglages communs des graphiques (Chart.js 4)
   --------------------------------------------------------------------------
   Centralise l'apparence de tous les visuels du portail pour qu'ils se
   lisent comme un seul système : même grille discrète, même infobulle,
   mêmes formats de nombres, mêmes épaisseurs de trait.

   Règles appliquées :
   • traits fins (2 px), points >= 8 px au survol, extrémités de barres
     arrondies (4 px) ancrées à la ligne de base ;
   • grille et axes en retrait, jamais en concurrence avec les données ;
   • infobulle au survol sur tous les visuels, en mode « index » sur les
     courbes pour comparer les séries à une même date ;
   • aucune information portée par la couleur seule : chaque graphique est
     doublé d'une légende et d'un tableau de valeurs.
   ========================================================================== */

(function () {
  'use strict';

  if (typeof Chart === 'undefined') { return; }

  const INK        = '#101828';
  const INK_2      = '#4a5567';
  const INK_MUTED  = '#7a8699';
  const GRID       = '#e8ecf3';
  const SURFACE    = '#ffffff';
  const LINE       = '#dfe4ec';

  // ── Formats de nombres (français : espace fine, virgule décimale) ───────
  const nf0 = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
  const nf2 = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  window.BIFmt = {
    n0: (v) => nf0.format(v),
    n2: (v) => nf2.format(v),
    /**
     * Format court pour les axes : 1,2 M / 420 K / 3,5 K
     *
     * Sous 10 000, on garde une décimale : sans elle, des graduations
     * distinctes (3 000 et 3 500) s'affichaient toutes les deux « 3 K »,
     * et l'axe présentait deux fois le même libellé.
     */
    compact(v) {
      const a = Math.abs(v);
      if (a >= 1e6) return nf2.format(v / 1e6).replace(/,00$/, '') + ' M';
      if (a >= 1e4) return nf0.format(v / 1e3) + ' K';
      if (a >= 1e3) {
        return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 })
          .format(v / 1e3) + ' K';
      }
      return nf0.format(v);
    },
    pct: (v, d = 2) => new Intl.NumberFormat('fr-FR', {
      minimumFractionDigits: d, maximumFractionDigits: d,
    }).format(v) + ' %',
  };

  // ── Réglages globaux ────────────────────────────────────────────────────
  Chart.defaults.font.family =
    'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", sans-serif';
  Chart.defaults.font.size = 11;
  Chart.defaults.color = INK_MUTED;
  Chart.defaults.maintainAspectRatio = false;
  Chart.defaults.responsive = true;

  // Infobulle commune — fond clair, cadre discret, valeurs alignées.
  Chart.defaults.plugins.tooltip = Object.assign(Chart.defaults.plugins.tooltip, {
    backgroundColor: SURFACE,
    titleColor: INK,
    bodyColor: INK_2,
    borderColor: LINE,
    borderWidth: 1,
    padding: 11,
    cornerRadius: 7,
    boxPadding: 5,
    usePointStyle: true,
    titleFont: { weight: '650', size: 12 },
    bodyFont: { size: 11.5 },
    displayColors: true,
  });

  // La légende native est remplacée par une légende HTML sous chaque
  // graphique : elle reste lisible à l'impression et ne se tronque pas
  // sur petit écran.
  Chart.defaults.plugins.legend.display = false;

  /** Axe des valeurs : grille très légère, pas de ligne d'axe. */
  window.BIAxisY = (opts = {}) => Object.assign({
    beginAtZero: true,
    border: { display: false },
    grid: { color: GRID, drawTicks: false },
    ticks: { color: INK_MUTED, padding: 8, callback: (v) => window.BIFmt.compact(v) },
  }, opts);

  /** Axe des catégories : pas de grille verticale, ligne de base visible. */
  window.BIAxisX = (opts = {}) => Object.assign({
    border: { color: '#c6cedd' },
    grid: { display: false },
    ticks: { color: INK_MUTED, padding: 6, maxRotation: 0, autoSkipPadding: 12 },
  }, opts);

  /**
   * Trace une légende HTML sous un graphique.
   * @param {string} id      identifiant du conteneur
   * @param {Array}  items   [{label, color}]
   */
  window.BILegend = function (id, items) {
    const host = document.getElementById(id);
    if (!host) { return; }
    host.innerHTML = items.map((i) =>
      '<span class="item"><span class="dot" style="background:' + i.color + '"></span>' +
      i.label + '</span>'
    ).join('');
  };

  /**
   * Réticule vertical au survol des courbes : aide à lire la valeur
   * exacte à une date donnée sur une série dense.
   */
  Chart.register({
    id: 'crosshair',
    afterDatasetsDraw(chart) {
      const active = chart.tooltip?.getActiveElements?.() || [];
      if (!active.length || chart.config.type !== 'line') { return; }
      const { ctx, chartArea } = chart;
      const x = active[0].element.x;
      ctx.save();
      ctx.beginPath();
      ctx.moveTo(x, chartArea.top);
      ctx.lineTo(x, chartArea.bottom);
      ctx.lineWidth = 1;
      ctx.strokeStyle = '#c6cedd';
      ctx.stroke();
      ctx.restore();
    },
  });
})();
