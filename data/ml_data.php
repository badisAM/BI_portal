<?php
/**
 * data/ml_data.php — Résultats des modèles de prédiction et de data mining
 *
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │ POURQUOI CE FICHIER EXISTE                                           │
 * │                                                                      │
 * │ La version initiale de dashboard_ml.php exécutait Python depuis PHP :│
 * │                                                                      │
 * │     $MODELS_DIR = "C:/xampp/htdocs/bi_portal/models";                │
 * │     shell_exec("python /tmp/xxx.py")                                 │
 * │                                                                      │
 * │ Trois raisons rendaient ce montage indéployable :                    │
 * │                                                                      │
 * │  1. Chemin absolu Windows — inexistant sur tout autre serveur.       │
 * │  2. shell_exec() est désactivé par défaut chez la quasi-totalité des │
 * │     hébergeurs, et un serveur web n'a ni Python, ni joblib, ni       │
 * │     scikit-learn, ni prophet installés.                              │
 * │  3. Le script Prophet ouvrait en plus une connexion pyodbc vers SQL  │
 * │     Server avec les identifiants en clair (sa / Bedis123). Ces       │
 * │     identifiants se retrouvaient publiés sur GitHub.                 │
 * │                                                                      │
 * │ Ici, les SORTIES des modèles sont figées dans des tableaux PHP.      │
 * │ Le portail affiche donc les mêmes analyses, sans Python, sans base,  │
 * │ et sans secret dans le dépôt.                                        │
 * └──────────────────────────────────────────────────────────────────────┘
 *
 * ── Ce qui est réel et ce qui est reconstitué ─────────────────────────────
 *
 *  RÉEL (extrait des fichiers du dépôt, models/*.csv) :
 *    • CLUSTERS        ← agrégation de models/clusters_produits.csv (177 produits)
 *    • REGLES          ← models/association_rules.csv (FP-Growth)
 *    • CATALOGUE       ← prix et volumes réels par produit
 *
 *  RECONSTITUÉ (les .pkl ne sont pas exécutables ici) :
 *    • PREVISION       ← série hebdomadaire, calée sur les totaux réels
 *    • predire_ca()    ← modèle de substitution, documenté ci-dessous
 */

declare(strict_types=1);

require_once __DIR__ . '/bi_data.php';

// ══════════════════════════════════════════════════════════════════════════
//  1. CATALOGUE PRODUITS — données réelles
//     Source : models/clusters_produits.csv (18 premiers produits par CA)
//     [id, nom, prix unitaire, nb commandes, CA total, cluster]
// ══════════════════════════════════════════════════════════════════════════

const CATALOGUE = [
    ['id' =>  1, 'nom' => 'Express',           'prix' => 1.90, 'cmd' => 91_847, 'ca' => 160_534.45, 'cluster' => 0],
    ['id' =>  2, 'nom' => 'Cappucin',          'prix' => 2.20, 'cmd' => 63_820, 'ca' => 125_362.30, 'cluster' => 0],
    ['id' =>  3, 'nom' => 'Camel bleu',        'prix' => 0.55, 'cmd' => 57_008, 'ca' =>  96_045.14, 'cluster' => 0],
    ['id' =>  4, 'nom' => 'Légère',            'prix' => 0.35, 'cmd' => 65_971, 'ca' =>  90_428.60, 'cluster' => 0],
    ['id' =>  5, 'nom' => 'Direct',            'prix' => 2.50, 'cmd' => 40_642, 'ca' =>  89_367.34, 'cluster' => 3],
    ['id' =>  6, 'nom' => 'Soufflé',           'prix' => 2.50, 'cmd' => 22_440, 'ca' =>  65_510.00, 'cluster' => 3],
    ['id' =>  7, 'nom' => 'Eau minérale 1.5',  'prix' => 1.50, 'cmd' => 33_249, 'ca' =>  46_755.64, 'cluster' => 3],
    ['id' =>  8, 'nom' => 'Canette',           'prix' => 2.50, 'cmd' => 17_916, 'ca' =>  38_968.96, 'cluster' => 3],
    ['id' =>  9, 'nom' => 'Malboro Gold',      'prix' => 0.60, 'cmd' => 21_144, 'ca' =>  37_814.65, 'cluster' => 3],
    ['id' => 10, 'nom' => 'Tranche Pizza',     'prix' => 4.50, 'cmd' =>  6_489, 'ca' =>  33_156.90, 'cluster' => 1],
    ['id' => 11, 'nom' => 'Sandwich escalope', 'prix' => 6.50, 'cmd' =>  4_554, 'ca' =>  31_916.30, 'cluster' => 2],
    ['id' => 12, 'nom' => 'Américain',         'prix' => 1.90, 'cmd' => 18_284, 'ca' =>  30_535.86, 'cluster' => 3],
    ['id' => 13, 'nom' => 'Pain au chocolat',  'prix' => 1.80, 'cmd' => 15_117, 'ca' =>  27_787.62, 'cluster' => 3],
    ['id' => 14, 'nom' => 'Paté',              'prix' => 1.80, 'cmd' => 13_483, 'ca' =>  27_001.30, 'cluster' => 3],
    ['id' => 15, 'nom' => 'Tropico',           'prix' => 1.50, 'cmd' => 18_557, 'ca' =>  26_543.91, 'cluster' => 3],
    ['id' => 16, 'nom' => "Jus d'orange",      'prix' => 3.50, 'cmd' =>  8_280, 'ca' =>  22_687.79, 'cluster' => 1],
    ['id' => 17, 'nom' => 'Eau minérale 0.5',  'prix' => 1.00, 'cmd' => 26_489, 'ca' =>  21_719.42, 'cluster' => 3],
    ['id' => 18, 'nom' => 'Makloub escalope',  'prix' => 8.50, 'cmd' =>  2_239, 'ca' =>  21_088.10, 'cluster' => 2],
];

/** Retrouve un produit du catalogue. */
function produit(int $id): ?array
{
    foreach (CATALOGUE as $p) {
        if ($p['id'] === $id) {
            return $p;
        }
    }
    return null;
}

// ══════════════════════════════════════════════════════════════════════════
//  2. SEGMENTATION K-MEANS — données réelles
//     Agrégation de models/clusters_produits.csv : 177 produits, 4 groupes.
// ══════════════════════════════════════════════════════════════════════════

/**
 * Note de lecture importante.
 *
 * Le code d'origine associait les numéros de clusters à ces libellés :
 *     0 → Top Vendeur | 1 → Produit Lent | 2 → Produit Moyen | 3 → Premium
 *
 * Or les chiffres du CSV racontent autre chose : le cluster 2 réunit
 * 66 produits à 6,95 DT de prix moyen et 405 unités écoulées — c'est lui,
 * le segment premium à faible rotation, pas le 3. Et le cluster 3 (11
 * produits, 33 000 unités) est un segment de forte rotation, pas du premium.
 *
 * Les libellés sont donc corrigés ici pour coller aux données.
 */
const CLUSTERS = [
    [
        'id' => 0, 'nom' => 'Locomotives',
        'desc' => "Le socle du chiffre d'affaires : très forte rotation, prix bas.",
        'nb' => 4,  'ca_moyen' => 118_092.62, 'qte_moyenne' => 158_749.2,
        'cmd_moyenne' => 69_661.5, 'prix_moyen' => 1.250, 'ca_total' => 472_370.48,
        'exemples' => ['Express', 'Cappucin', 'Camel bleu', 'Légère'],
        'couleur' => '#2a78d6',
    ],
    [
        'id' => 3, 'nom' => 'Forte rotation',
        'desc' => 'Volumes élevés et réguliers, marge unitaire modérée.',
        'nb' => 11, 'ca_moyen' => 38_080.61, 'qte_moyenne' => 32_986.5,
        'cmd_moyenne' => 25_735.9, 'prix_moyen' => 1.609, 'ca_total' => 418_886.71,
        'exemples' => ['Direct', 'Soufflé', 'Eau minérale 1.5', 'Canette'],
        'couleur' => '#eb6834',
    ],
    [
        'id' => 1, 'nom' => 'Longue traîne',
        'desc' => 'Le plus gros effectif, mais chaque référence pèse peu.',
        'nb' => 96, 'ca_moyen' => 3_118.37, 'qte_moyenne' => 2_206.3,
        'cmd_moyenne' => 1_614.5, 'prix_moyen' => 2.094, 'ca_total' => 299_363.52,
        'exemples' => ['Tranche Pizza', "Jus d'orange", 'Thé', 'Citronnade'],
        'couleur' => '#1baf7a',
    ],
    [
        'id' => 2, 'nom' => 'Premium faible volume',
        'desc' => 'Prix élevé (6,95 DT en moyenne), rotation lente.',
        'nb' => 66, 'ca_moyen' => 2_472.17, 'qte_moyenne' => 405.2,
        'cmd_moyenne' => 354.7, 'prix_moyen' => 6.953, 'ca_total' => 163_163.22,
        'exemples' => ['Sandwich escalope', 'Makloub escalope', 'Sandwich thon', 'Chawarma'],
        'couleur' => '#eda100',
    ],
];

const CLUSTERS_NB_PRODUITS = 177;

// ══════════════════════════════════════════════════════════════════════════
//  3. RÈGLES D'ASSOCIATION — données réelles
//     Source : models/association_rules.csv (algorithme FP-Growth)
//
//     support    : fréquence du couple dans l'ensemble des tickets
//     confiance  : P(conséquent | antécédent)
//     lift       : > 1 → les deux produits s'achètent ensemble plus
//                  souvent que le hasard ne le prévoit
// ══════════════════════════════════════════════════════════════════════════

const REGLES = [
    ['si' => 'Pain au chocolat',  'alors' => 'Direct',     'support' => 0.005606, 'confiance' => 0.266476, 'lift' => 3.127],
    ['si' => 'Gobelets',          'alors' => 'Direct',     'support' => 0.005636, 'confiance' => 0.196017, 'lift' => 2.300],
    ['si' => 'Pain au chocolat',  'alors' => 'Cappucin',   'support' => 0.006239, 'confiance' => 0.296562, 'lift' => 2.219],
    ['si' => 'Gobelets',          'alors' => 'Cappucin',   'support' => 0.008229, 'confiance' => 0.286164, 'lift' => 2.141],
    ['si' => 'Gobelets',          'alors' => 'Express',    'support' => 0.009012, 'confiance' => 0.313417, 'lift' => 1.834],
    ['si' => 'Croustina',         'alors' => 'Cappucin',   'support' => 0.005456, 'confiance' => 0.147755, 'lift' => 1.106],
    ['si' => 'Eau minérale 1.5',  'alors' => 'Express',    'support' => 0.013594, 'confiance' => 0.174064, 'lift' => 1.019],
    ['si' => 'Tropico',           'alors' => 'Camel bleu', 'support' => 0.007505, 'confiance' => 0.156801, 'lift' => 1.018],
];

// ══════════════════════════════════════════════════════════════════════════
//  4. PRÉDICTION DE CA — modèle de substitution
// ══════════════════════════════════════════════════════════════════════════

/**
 * Estime le chiffre d'affaires d'un produit sur un mois donné.
 *
 * ⚠ CE N'EST PAS le Random Forest de models/rf_ca_heure.pkl.
 *
 * Le .pkl ne peut pas être lu par PHP : il faudrait Python, joblib et
 * scikit-learn côté serveur. Cette fonction est un modèle de substitution
 * — volontairement simple et entièrement lisible — qui reproduit
 * l'interface du Random Forest (mêmes entrées, même sortie) à partir des
 * statistiques réelles du catalogue :
 *
 *     CA = nb_commandes × panier_moyen_du_produit
 *          × coefficient_saisonnier(mois)
 *          × coefficient_de_tendance(année)
 *
 * où panier_moyen_du_produit = CA total observé / nb de commandes observées.
 *
 * Pour brancher le vrai modèle : voir la section « Remettre les vrais
 * modèles en service » du README.
 *
 * @return array{ok: bool, ca?: float, detail?: array, error?: string}
 */
function predire_ca(int $idProduit, int $mois, int $annee, int $nbCommandes): array
{
    $p = produit($idProduit);
    if ($p === null) {
        return ['ok' => false, 'error' => "Produit #$idProduit inconnu au catalogue."];
    }
    if ($mois < 1 || $mois > 12) {
        return ['ok' => false, 'error' => 'Le mois doit être compris entre 1 et 12.'];
    }
    if ($nbCommandes < 0) {
        return ['ok' => false, 'error' => 'Le nombre de commandes ne peut pas être négatif.'];
    }

    $panier = $p['ca'] / max(1, $p['cmd']);   // CA moyen par commande
    $cm     = coef_mois()[$mois];
    $ca     = coef_annee($annee);

    return [
        'ok' => true,
        'ca' => round($nbCommandes * $panier * $cm * $ca, 2),
        'detail' => [
            'produit'      => $p['nom'],
            'panier'       => round($panier, 4),
            'coef_mois'    => $cm,
            'coef_annee'   => $ca,
            'nb_commandes' => $nbCommandes,
            'prix'         => $p['prix'],
        ],
    ];
}

// ══════════════════════════════════════════════════════════════════════════
//  5. PRÉVISION DE CHIFFRE D'AFFAIRES — série hebdomadaire
// ══════════════════════════════════════════════════════════════════════════

/**
 * Historique (26 semaines) suivi d'une prévision (12 semaines).
 *
 * Reconstitue ce que produisait le bloc Prophet du tableau de bord
 * d'origine : une courbe hebdomadaire prolongée par une projection
 * encadrée d'un intervalle de confiance.
 *
 * L'intervalle s'élargit avec l'horizon — c'est le comportement attendu
 * d'une prévision : plus on projette loin, moins on est précis.
 *
 * @return array<int, array{ds:string, yhat:float, bas:float, haut:float, futur:bool}>
 */
function previsions(): array
{
    mt_srand(20241231); // graine fixe → série reproductible

    $rows    = [];
    $semaine = new DateTimeImmutable('2024-07-01'); // lundi

    for ($i = 0; $i < 38; $i++) {
        $annee = (int) $semaine->format('Y');

        // CA hebdomadaire moyen = CA annuel / 52, modulé par la saison.
        // Le coefficient est interpolé au jour : sans cela, les quatre
        // semaines d'un même mois affichaient une valeur identique.
        $base = (array_sum(VENTES_MENSUEL[2024]) * 1000 / 52)
              * coef_saison_jour($semaine)
              * coef_annee($annee);

        $futur = $i >= 26;
        $bruit = $futur ? 1.0 : 1 + (mt_rand(-90, 90) / 1000); // le passé est bruité, la prévision est lissée

        $yhat = $base * $bruit;

        // Largeur de l'intervalle : 9 % sur l'historique, jusqu'à ~22 %
        // au bout de douze semaines de projection.
        $marge = $futur ? 0.09 + 0.011 * ($i - 25) : 0.09;

        $rows[] = [
            'ds'    => $semaine->format('Y-m-d'),
            'yhat'  => round($yhat, 2),
            'bas'   => round($yhat * (1 - $marge), 2),
            'haut'  => round($yhat * (1 + $marge), 2),
            'futur' => $futur,
        ];

        $semaine = $semaine->modify('+1 week');
    }

    return $rows;
}
