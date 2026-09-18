<?php
/**
 * data/bi_data.php — Jeu de données des rapports répliqués
 *
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │ PROVENANCE DES CHIFFRES                                              │
 * │                                                                      │
 * │ • Les tableaux détaillés (produits, modes de paiement) et les        │
 * │   indicateurs sont relevés directement sur les rapports Power BI du  │
 * │   projet. Ils sont cohérents : les colonnes se totalisent exactement │
 * │   aux totaux affichés, et les pourcentages des anneaux se recalculent│
 * │   à partir des montants (Camel bleu : 90 907,94 / 374 907,39 =       │
 * │   24,25 %, valeur identique au rapport d'origine).                   │
 * │                                                                      │
 * │ • Les séries temporelles (mensuel, trimestriel, journalier) sont     │
 * │   RECONSTITUÉES : les rapports n'en donnent que la forme, pas les    │
 * │   valeurs point par point. Elles respectent les totaux réels         │
 * │   (1,20 M DT de CA) et la saisonnalité observée. Elles sont          │
 * │   signalées comme reconstituées sur les pages concernées.            │
 * └──────────────────────────────────────────────────────────────────────┘
 *
 * Toutes les valeurs monétaires sont en dinars tunisiens (DT).
 */

declare(strict_types=1);

// ══════════════════════════════════════════════════════════════════════════
//  PAGE 1 — VUE GÉNÉRALE SUR VENTE
// ══════════════════════════════════════════════════════════════════════════

/** Indicateurs de tête (relevés sur le rapport). */
const VENTES_KPI = [
    ['lbl' => 'CA Total',           'val' => 1_200_000, 'fmt' => 'compact', 'unit' => 'DT',
     'note' => 'Période 2022 – 2024',            'accent' => 'var(--s1)'],
    ['lbl' => 'Nb Commandes',       'val' => 420_000,   'fmt' => 'compact', 'unit' => '',
     'note' => 'Tickets encaissés',              'accent' => 'var(--s7)'],
    ['lbl' => 'Quantité Totale',    'val' => 1_110_000, 'fmt' => 'compact', 'unit' => 'u.',
     'note' => 'Articles vendus',                'accent' => 'var(--s3)'],
    ['lbl' => 'Panier Moyen',       'val' => 2.85,      'fmt' => 'money2',  'unit' => 'DT',
     'note' => 'CA / nombre de commandes',       'accent' => 'var(--s4)'],
    ['lbl' => 'Croissance YoY',     'val' => 0.94,      'fmt' => 'pct100',  'unit' => '',
     'note' => "D'une année sur l'autre",  'accent' => 'var(--s6)', 'trend' => 'up'],
    ['lbl' => 'Croissance MoM',     'val' => 0.06,      'fmt' => 'pct100',  'unit' => '',
     'note' => "D'un mois sur l'autre",    'accent' => 'var(--s2)', 'trend' => 'up'],
];

/** CA par trimestre, en DT (reconstitué — somme = 1,20 M). */
const VENTES_TRIMESTRES = [
    'T1' => 285_000,
    'T2' => 250_000,
    'T3' => 205_000,
    'T4' => 460_000,
];

/** Mois de l'année, dans l'ordre chronologique. */
const MOIS = ['Janv', 'Févr', 'Mars', 'Avr', 'Mai', 'Juin',
              'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];

/**
 * CA mensuel par année, en milliers de DT (reconstitué).
 *
 * Note de lecture : le rapport Power BI d'origine triait les mois par
 * ordre alphabétique du libellé (octobre, novembre, décembre, février…),
 * ce qui rendait la saisonnalité illisible. Ici les mois sont remis dans
 * l'ordre chronologique — c'est l'un des correctifs de lisibilité.
 *
 * Contrôle de cohérence : chaque ligne se totalise au CA de l'année, et
 * les trimestres se recomposent exactement aux valeurs de VENTES_TRIMESTRES.
 */
const VENTES_MENSUEL = [
    //        J   F   M   A   M   J   J   A   S   O   N   D      total
    2022 => [22, 23, 26, 21, 21, 21, 16, 16, 19, 36, 38, 41], // 300 K
    2023 => [28, 30, 32, 26, 26, 27, 21, 21, 23, 46, 48, 52], // 380 K
    2024 => [39, 41, 44, 35, 36, 37, 28, 29, 32, 63, 66, 70], // 520 K
];

/** Couleur attribuée à chaque année (ordre fixe de la palette). */
const VENTES_ANNEES_COULEURS = [2022 => 'var(--s3)', 2023 => 'var(--s1)', 2024 => 'var(--s2)'];

/**
 * Série journalière du CA (reconstituée).
 *
 * Reproduit la forme du visuel « CA Cumulé par Année, Trimestre, Mois et
 * Jour » : oscillation hebdomadaire marquée, creux estivaux, pic de fin
 * d'année, tendance haussière sur trois ans.
 *
 * Générée par un tirage pseudo-aléatoire à graine fixe : la courbe est
 * donc identique à chaque affichage, ce qui évite de stocker un millier
 * de points en dur.
 */
function ca_journalier(): array
{
    mt_srand(20220214); // graine fixe → série reproductible

    $out   = [];
    $start = new DateTimeImmutable('2022-02-14');
    $end   = new DateTimeImmutable('2024-12-31');
    $day   = $start;

    // Poids mensuel issu de VENTES_MENSUEL, pour que la courbe journalière
    // raconte la même histoire que le graphique mensuel.
    while ($day <= $end) {
        $y = (int) $day->format('Y');
        $w = (int) $day->format('N'); // 1 = lundi … 7 = dimanche

        // CA moyen journalier de l'année, modulé par une saisonnalité
        // interpolée au jour. Appliquer directement le total du mois
        // produisait des paliers nets au passage d'un mois à l'autre.
        $base = (array_sum(VENTES_MENSUEL[$y] ?? []) * 1000 / 365)
              * coef_saison_jour($day);

        // Saisonnalité hebdomadaire : creux en début de semaine,
        // pic vendredi/samedi.
        $sem = [1 => 0.82, 2 => 0.86, 3 => 0.92, 4 => 1.02, 5 => 1.28, 6 => 1.34, 7 => 0.76][$w];

        $bruit = 1 + (mt_rand(-170, 170) / 1000); // ±17 %

        $out[] = [
            'd' => $day->format('Y-m-d'),
            'v' => round($base * $sem * $bruit, 2),
        ];
        $day = $day->modify('+1 day');
    }

    return $out;
}

// ══════════════════════════════════════════════════════════════════════════
//  PAGE 2 — PERFORMANCE PRODUITS
// ══════════════════════════════════════════════════════════════════════════

/**
 * Top 10 produits — relevé intégral du rapport.
 *
 * qte       : quantité vendue
 * ca        : chiffre d'affaires (DT)
 * commandes : nombre de commandes (source : models/clusters_produits.csv)
 *
 * Contrôle : sum(qte) = 655 138,00 et sum(ca) = 374 907,39 — identiques
 * à la ligne « Total » du rapport d'origine.
 */
const PRODUITS = [
    ['nom' => 'Camel bleu',       'qte' => 173_389.0, 'ca' => 90_907.94, 'commandes' => 57_008],
    ['nom' => 'Légère',           'qte' => 270_029.0, 'ca' => 89_150.00, 'commandes' => 65_971],
    ['nom' => 'Eau minérale 1.5', 'qte' =>  35_039.0, 'ca' => 45_845.54, 'commandes' => 33_249],
    ['nom' => 'Canette',          'qte' =>  20_034.0, 'ca' => 37_718.26, 'commandes' => 17_916],
    ['nom' => 'Malboro Gold',     'qte' =>  62_785.0, 'ca' => 36_093.15, 'commandes' => 21_144],
    ['nom' => 'Tropico',          'qte' =>  19_731.0, 'ca' => 25_930.71, 'commandes' => 18_557],
    ['nom' => 'Eau minérale 0.5', 'qte' =>  27_617.0, 'ca' => 21_218.60, 'commandes' => 26_489],
    ['nom' => 'Malboro rouge',    'qte' =>  17_246.0, 'ca' => 10_050.57, 'commandes' =>  6_120],
    ['nom' => 'Malboro Touch',    'qte' =>  15_698.0, 'ca' =>  9_255.69, 'commandes' =>  5_540],
    ['nom' => 'Croustina',        'qte' =>  13_570.0, 'ca' =>  8_736.93, 'commandes' => 12_187],
];

/** Segments reproduits depuis le rapport (décoratifs). */
const PRODUITS_FILTRES = [
    'nom_client'   => ['Client passager', 'Personnel', 'Retour'],
    'type_produit' => ['consu', 'service'],
    'periode'      => '14/02/2022 → 31/12/2025',
];

// ══════════════════════════════════════════════════════════════════════════
//  PAGE 3 — ANALYSE DES PAIEMENTS
// ══════════════════════════════════════════════════════════════════════════

/** Indicateurs de tête (relevés sur le rapport). */
const PAIEMENTS_KPI = [
    ['lbl' => 'Total Montant',   'val' => 1_376_531.00, 'fmt' => 'compact', 'unit' => 'DT',
     'note' => 'Somme encaissée',                'accent' => 'var(--s1)'],
    ['lbl' => 'Total Reçu',      'val' => 1_376_531.00, 'fmt' => 'compact', 'unit' => 'DT',
     'note' => 'Montant perçu',                  'accent' => 'var(--s3)'],
    ['lbl' => 'Total Rendu',     'val' => 3_960.00,     'fmt' => 'compact', 'unit' => 'DT',
     'note' => 'Monnaie rendue',                 'accent' => 'var(--s4)'],
    ['lbl' => 'Nb Transactions', 'val' => 473_483,      'fmt' => 'compact', 'unit' => '',
     'note' => 'Toutes caisses',                 'accent' => 'var(--s7)'],
    ['lbl' => 'Trop payées',     'val' => 178,          'fmt' => 'int',     'unit' => '',
     'note' => '0,04 % des transactions',        'accent' => 'var(--s2)'],
    ['lbl' => 'Sous-payées',     'val' => 2,            'fmt' => 'int',     'unit' => '',
     'note' => 'Écart négligeable',              'accent' => 'var(--s5)'],
];

/**
 * Répartition par mode de paiement — relevé intégral du rapport.
 *
 * Contrôle : sum(montant) = 1 376 531,00 et sum(nb) = 473 483 — identiques
 * à la ligne « Total » du rapport d'origine.
 *
 * manquant : « Montant Manquant » du visuel correspondant (seul TR en a).
 */
const PAIEMENTS = [
    ['code' => 'ESP', 'lib' => 'Espèces',       'montant' => 1_325_793.42, 'nb' => 466_075, 'manquant' => 0.00],
    ['code' => 'TR',  'lib' => 'Ticket resto',  'montant' =>    46_817.15, 'nb' =>   5_556, 'manquant' => 10.00],
    ['code' => 'CB',  'lib' => 'Carte bancaire','montant' =>     3_918.68, 'nb' =>   1_851, 'manquant' => 0.00],
    ['code' => 'PER', 'lib' => 'Compte perso',  'montant' =>         1.75, 'nb' =>       1, 'manquant' => 0.00],
];

/** Taux de recouvrement affiché par la jauge du rapport (max 2,01). */
const TAUX_RECOUVREMENT     = 1.00;
const TAUX_RECOUVREMENT_MAX = 2.01;

// ══════════════════════════════════════════════════════════════════════════
//  COEFFICIENTS SAISONNIERS ET DE TENDANCE
//  Dérivés de VENTES_MENSUEL — une seule source pour la saisonnalité,
//  utilisée aussi bien par la série journalière que par les modèles.
// ══════════════════════════════════════════════════════════════════════════

/**
 * Coefficient multiplicateur par mois (1 à 12), moyenne = 1.
 *
 * Décembre ressort à ~1,63 et juillet à ~0,65 : l'activité de fin d'année
 * vaut deux fois et demie celle du creux estival.
 */
function coef_mois(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $parMois = array_fill(0, 12, 0.0);
    foreach (VENTES_MENSUEL as $vals) {
        foreach ($vals as $i => $v) {
            $parMois[$i] += $v;
        }
    }
    $moyenne = array_sum($parMois) / 12;

    $cache = [];
    foreach ($parMois as $i => $somme) {
        $cache[$i + 1] = round($somme / $moyenne, 4);
    }
    return $cache;
}

/**
 * Coefficient saisonnier interpolé pour une date précise.
 *
 * Appliquer tel quel le coefficient du mois donne une courbe en escalier :
 * toutes les semaines d'un même mois sortent à la valeur exacte, puis la
 * série saute au 1er du mois suivant. On interpole donc linéairement entre
 * les coefficients des mois voisins, chacun étant considéré comme centré
 * au milieu de son mois. La saisonnalité devient continue, ce qu'elle est
 * dans la réalité.
 */
function coef_saison_jour(DateTimeImmutable $d): float
{
    $coefs = coef_mois();
    $mois  = (int) $d->format('n');
    $jour  = (int) $d->format('j');
    $dans  = (int) $d->format('t');   // nombre de jours du mois

    // Position dans le mois, de 0 (1er) à 1 (dernier jour).
    $pos = ($jour - 0.5) / $dans;

    $precedent = $coefs[$mois === 1  ? 12 : $mois - 1];
    $courant   = $coefs[$mois];
    $suivant   = $coefs[$mois === 12 ? 1  : $mois + 1];

    if ($pos < 0.5) {
        // Première moitié du mois : on vient du mois précédent.
        $w = $pos + 0.5;
        return $precedent * (1 - $w) + $courant * $w;
    }

    // Seconde moitié : on se dirige vers le mois suivant.
    $w = $pos - 0.5;
    return $courant * (1 - $w) + $suivant * $w;
}

/**
 * Coefficient de tendance par année, base 2023 = 1.
 * Au-delà de la dernière année observée, la progression moyenne est prolongée.
 */
function coef_annee(int $annee): float
{
    $totaux = [];
    foreach (VENTES_MENSUEL as $a => $vals) {
        $totaux[$a] = array_sum($vals);
    }
    $base = $totaux[2023];

    if (isset($totaux[$annee])) {
        return round($totaux[$annee] / $base, 4);
    }

    // Extrapolation : taux de croissance annuel moyen observé.
    $annees = array_keys($totaux);
    $premier = min($annees);
    $dernier = max($annees);
    $tcam = ($totaux[$dernier] / $totaux[$premier]) ** (1 / ($dernier - $premier));

    $ref = $totaux[$dernier] / $base;
    return round($ref * ($tcam ** max(0, $annee - $dernier)), 4);
}

// ══════════════════════════════════════════════════════════════════════════
//  OUTILS DE CALCUL
// ══════════════════════════════════════════════════════════════════════════

/** Somme d'une colonne d'un tableau de lignes. */
function col_sum(array $rows, string $key): float
{
    return array_sum(array_column($rows, $key));
}

/** Les 8 couleurs de séries, dans l'ordre fixe de la palette. */
function serie_colors(): array
{
    return ['var(--s1)', 'var(--s2)', 'var(--s3)', 'var(--s4)',
            'var(--s5)', 'var(--s6)', 'var(--s7)', 'var(--s8)'];
}

/** Valeurs hexadécimales correspondantes (pour Chart.js, qui ne lit pas les variables CSS). */
function serie_hex(): array
{
    return ['#2a78d6', '#eb6834', '#1baf7a', '#eda100',
            '#e87ba4', '#008300', '#4a3aa7', '#e34948'];
}

/**
 * Formate une valeur d'indicateur selon son type.
 */
function kpi_value(array $k): string
{
    return match ($k['fmt']) {
        'compact' => compact_num((float) $k['val']),
        'money2'  => money((float) $k['val'], 2),
        'pct100'  => money((float) $k['val'], 2),
        'int'     => number_format((float) $k['val'], 0, ',', ' '),
        default   => (string) $k['val'],
    };
}
