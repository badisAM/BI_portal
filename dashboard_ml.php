<?php
include("config.php");
if(!isset($_SESSION['role']) || $_SESSION['role']!="admin"){
    header("Location: login.php"); exit;
}

$MODELS_DIR = "C:/xampp/htdocs/bi_portal/models";
$PYTHON = "python";

function py(string $script): array {
    global $PYTHON;
    $tmp = tempnam(sys_get_temp_dir(), 'dash_') . '.py';
    file_put_contents($tmp, $script);
    $out = shell_exec("$PYTHON " . escapeshellarg($tmp) . " 2>&1");
    unlink($tmp);
    // Ignore warnings/prints parasites — prend la dernière ligne JSON valide
    foreach(array_reverse(explode("\n", $out)) as $line){
        $line = trim($line);
        if($line === '') continue;
        $data = json_decode($line, true);
        if(is_array($data)) return $data;
    }
    return ['error' => trim($out)];
}

// ── 1. Prophet forecast + historical series ──────────────────────────────────
$prophet_data = py(<<<PY
import joblib, json, warnings, numpy as np, pandas as pd
warnings.filterwarnings('ignore')
try:
    m = joblib.load(r"$MODELS_DIR/prophet_ca.pkl")
    model_type = type(m).__name__

    # ── Cas 1 : Prophet ──────────────────────────────────────────────────────
    try:
        from prophet import Prophet
        is_prophet = isinstance(m, Prophet)
    except Exception:
        is_prophet = False

    if is_prophet:
        fut = m.make_future_dataframe(periods=8, freq='W')
        fc  = m.predict(fut)
        hist = fc.tail(34)[['ds','yhat','yhat_lower','yhat_upper']]
        rows = []
        for i,(_, r) in enumerate(hist.iterrows()):
            rows.append({'ds': str(r.ds)[:10], 'yhat': round(r.yhat,2),
                'lower': round(r.yhat_lower,2), 'upper': round(r.yhat_upper,2),
                'is_future': i >= (len(hist)-8)})
        print(json.dumps({'ok':True,'rows':rows,'model':model_type}))

    # ── Cas 2 : XGBoost / LinearRegression avec lag features ─────────────────
    else:
        import os, pyodbc
        feat_path = r"$MODELS_DIR/prophet_ca_features.pkl"
        FEAT_COLS = joblib.load(feat_path) if os.path.exists(feat_path) else \
            ['t','month','quarter','week','lag_1','lag_2','lag_4','lag_8','lag_13','lag_26','rolling_4','rolling_13']

        # ── Charger historique réel depuis SSMS ──────────────────────────────
        conn = pyodbc.connect(
            'DRIVER={ODBC Driver 17 for SQL Server};SERVER=localhost;'
            'DATABASE=DataWarehouse_dhia;UID=sa;PWD=Bedis123;'
        )
        df = pd.read_sql("""
            SELECT DATEADD(DAY, 1-DATEPART(WEEKDAY, d.date_complete),
                           CAST(d.date_complete AS DATE)) as ds,
                   SUM(f.montant_ligne) as y
            FROM FAIT_VENTES f
            JOIN DIM_DATE d ON f.id_date = d.id_date
            GROUP BY DATEADD(DAY, 1-DATEPART(WEEKDAY, d.date_complete),
                             CAST(d.date_complete AS DATE))
            ORDER BY 1
        """, conn)
        conn.close()
        df['ds'] = pd.to_datetime(df['ds'])

        # Nettoyage outliers (même logique que notebook)
        Q_low  = df['y'].quantile(0.05)
        Q_high = df['y'].quantile(0.95)
        mask = (df['y'] < Q_low) | (df['y'] > Q_high)
        df.loc[mask, 'y'] = np.nan
        df['y'] = df['y'].interpolate(method='linear')

        # ── Prévision récursive 12 semaines (exacte comme notebook) ──────────
        df_ext = df.copy()
        for _ in range(12):
            next_ds = df_ext['ds'].max() + pd.Timedelta(weeks=1)
            row = pd.DataFrame({'ds':[next_ds], 'y':[np.nan]})
            df_ext = pd.concat([df_ext, row], ignore_index=True)
            # add_lags inline
            d = df_ext.copy()
            d['t']       = np.arange(len(d))
            d['month']   = d['ds'].dt.month
            d['quarter'] = d['ds'].dt.quarter
            d['week']    = d['ds'].dt.isocalendar().week.astype(int)
            for lag in [1,2,4,8,13,26]:
                d[f'lag_{lag}'] = d['y'].shift(lag)
            d['rolling_4']  = d['y'].shift(1).rolling(4).mean()
            d['rolling_13'] = d['y'].shift(1).rolling(13).mean()
            feats = d.iloc[[-1]][FEAT_COLS].fillna(0).values
            df_ext.loc[df_ext.index[-1], 'y'] = float(m.predict(feats)[0])

        # ── Construire les rows : 26 semaines historiques + 12 futures ───────
        hist_df   = df_ext[df_ext['ds'] <= df['ds'].max()].tail(26)
        future_df = df_ext[df_ext['ds'] >  df['ds'].max()]
        rows = []
        std = float(df['y'].std()) * 0.15  # intervalle confiance approximatif
        for _, r in hist_df.iterrows():
            rows.append({'ds': str(r.ds.date()), 'yhat': round(r.y,2),
                'lower': round(r.y - std,2), 'upper': round(r.y + std,2),
                'is_future': False})
        for _, r in future_df.iterrows():
            rows.append({'ds': str(r.ds.date()), 'yhat': round(r.y,2),
                'lower': round(r.y - std*1.5,2), 'upper': round(r.y + std*1.5,2),
                'is_future': True})

        print(json.dumps({'ok':True,'rows':rows,'model':model_type}))

except Exception as e:
    import traceback
    print(json.dumps({'ok':False,'error':traceback.format_exc()}))
PY);

// ── 2. RF prediction (manual input or sample) ────────────────────────────────
$rf_input = [
    'id_produit'   => (int)($_POST['id_produit']   ?? 1),
    'mois'         => (int)($_POST['mois']          ?? (int)date('m')),
    'annee'        => (int)($_POST['annee']         ?? (int)date('Y')),
    'nb_commandes' => (int)($_POST['nb_commandes']  ?? 5),
];
$submitted = isset($_POST['predict']);

$rf_result = py(<<<PY
import joblib, json, numpy as np, warnings
warnings.filterwarnings('ignore')
try:
    rf = joblib.load(r"$MODELS_DIR/rf_ca_heure.pkl")
    inp = np.array([[{$rf_input['id_produit']},{$rf_input['mois']},{$rf_input['annee']},{$rf_input['nb_commandes']}]])
    pred = float(rf.predict(inp)[0])
    print(json.dumps({'ok':True,'ca':round(pred,2)}))
except Exception as e:
    print(json.dumps({'ok':False,'error':str(e)}))
PY);

// ── 3. Clusters ───────────────────────────────────────────────────────────────
$cluster_data = py(<<<PY
import json, os, csv
f = r"$MODELS_DIR/clusters_produits.csv"
if os.path.exists(f):
    from collections import defaultdict
    sums = defaultdict(lambda:{'ca':0,'qte':0,'count':0})
    with open(f) as fh:
        for row in csv.DictReader(fh):
            c = row.get('cluster','?')
            sums[c]['ca']    += float(row.get('total_ca',0) or 0)
            sums[c]['qte']   += float(row.get('total_qte',0) or 0)
            sums[c]['count'] += 1
    labels_map = {'0':'Top Vendeur','1':'Produit Lent','2':'Produit Moyen','3':'Premium'}
    out=[{'cluster':labels_map.get(str(c), 'Cluster '+str(c)),'nb_produits':v['count'],
          'ca_moyen':round(v['ca']/v['count'],2),
          'qte_moyenne':round(v['qte']/v['count'],2)}
         for c,v in sorted(sums.items())]
    print(json.dumps({'ok':True,'rows':out}))
else:
    print(json.dumps({'ok':False,'error':'clusters_produits.csv introuvable — relancez notebook2'}))
PY);

// ── 4. Association rules ──────────────────────────────────────────────────────
$rules_data = py(<<<PY
import json, os, csv
f = r"$MODELS_DIR/association_rules.csv"
if os.path.exists(f):
    rows=[]
    with open(f) as fh:
        for i,row in enumerate(csv.DictReader(fh)):
            if i>=10: break
            rows.append({'antecedents':row.get('antecedents',''),
                'consequents':row.get('consequents',''),
                'lift':round(float(row.get('lift',0)),3),
                'confidence':round(float(row.get('confidence',0)),3)})
    print(json.dumps({'ok':True,'rows':rows}))
else:
    print(json.dumps({'ok':False,'error':'association_rules.csv introuvable — relancez notebook3'}))
PY);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard ML — Analytique</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,Segoe UI,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh}
header{background:#1e293b;padding:14px 28px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #334155;position:sticky;top:0;z-index:10}
header h1{font-size:1.1rem;font-weight:700;color:#38bdf8}
header nav a{color:#94a3b8;text-decoration:none;margin-left:18px;font-size:.83rem}
header nav a:hover{color:#e2e8f0}
.page{padding:22px 24px;display:grid;gap:20px}
.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:20px}
.card{background:#1e293b;border-radius:12px;padding:20px;border:1px solid #334155}
.card-full{grid-column:1/-1}
.card h2{font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.dot{width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0}
/* table */
.tbl{width:100%;border-collapse:collapse;font-size:.82rem}
.tbl th{color:#64748b;font-weight:600;text-align:left;padding:5px 8px;border-bottom:1px solid #334155}
.tbl td{padding:6px 8px;border-bottom:1px solid #ffffff08}
.tbl tr:last-child td{border:none}
.tbl tr:hover td{background:#ffffff06}
/* badges */
.badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.73rem;font-weight:600}
.b-blue{background:#0ea5e920;color:#38bdf8}
.b-green{background:#22c55e20;color:#4ade80}
.b-purple{background:#a855f720;color:#c084fc}
.b-orange{background:#f9731620;color:#fb923c}
.lift-bar{display:inline-block;height:6px;background:#38bdf8;border-radius:3px;vertical-align:middle;margin-left:6px}
/* error */
.err{color:#f87171;font-size:.78rem;padding:10px 12px;background:#7f1d1d33;border-radius:8px;border:1px solid #f8717133;white-space:pre-wrap;word-break:break-word}
/* predict form */
.predict-form{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:18px}
.fgroup{display:flex;flex-direction:column;gap:5px}
.fgroup label{font-size:.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.06em}
.fgroup input{background:#0f172a;border:1px solid #334155;color:#e2e8f0;padding:7px 12px;border-radius:7px;font-size:.85rem;width:130px}
.fgroup input:focus{outline:none;border-color:#38bdf8}
.btn{background:#0ea5e9;color:#fff;border:none;padding:8px 20px;border-radius:7px;font-size:.85rem;font-weight:600;cursor:pointer;align-self:flex-end}
.btn:hover{background:#0284c7}
/* result box */
.result-box{background:#0f172a;border-radius:10px;padding:16px 20px;display:flex;align-items:center;gap:16px;border:1px solid #0ea5e940;margin-top:4px}
.result-box .big{font-size:2rem;font-weight:800;color:#38bdf8}
.result-box .sub{font-size:.8rem;color:#64748b;margin-top:2px}
/* chart canvas */
.chart-wrap{position:relative;height:260px;margin-top:8px}
</style>
</head>
<body>
<header>
  <h1>📊 Dashboard ML &amp; Analytique</h1>
  <nav>
    <a href="admin.php">← Admin</a>
    <a href="config_employes.php">Utilisateurs</a>
    <a href="logout.php">Déconnexion</a>
  </nav>
</header>

<div class="page">

  <!-- ══════════════════════════════════════════════════════
       BLOC 1 — Prévision CA : courbe + tableau
  ══════════════════════════════════════════════════════ -->
  <div class="card">
    <h2><span class="dot" style="background:#38bdf8"></span>Évolution &amp; Prévision CA — Série temporelle
      <?php if(!empty($prophet_data['model'])): ?>
        <span class="badge b-blue" style="font-size:.7rem;margin-left:6px"><?= htmlspecialchars($prophet_data['model']) ?></span>
      <?php endif; ?>
    </h2>
    <?php if(!empty($prophet_data['error'])): ?>
      <div class="err">⚠ <?= htmlspecialchars($prophet_data['error']) ?></div>
      <?php if(str_contains($prophet_data['error']??'','prophet')): ?>
        <p style="color:#64748b;font-size:.78rem;margin-top:10px">💡 Fix : <code>pip install prophet</code></p>
      <?php endif; ?>
    <?php elseif(empty($prophet_data['ok'])): ?>
      <div class="err">Modèle Prophet non chargé.</div>
    <?php else: ?>
    <div class="chart-wrap">
      <canvas id="prophetChart"></canvas>
    </div>
    <div style="margin-top:14px">
      <table class="tbl">
        <tr><th>Semaine</th><th>CA prédit</th><th>Borne inf.</th><th>Borne sup.</th><th></th></tr>
        <?php foreach(array_slice($prophet_data['rows'], -8) as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['ds']) ?></td>
          <td><span class="badge b-blue"><?= number_format($r['yhat'],2,'.',' ') ?></span></td>
          <td style="color:#64748b"><?= number_format($r['lower'],2,'.',' ') ?></td>
          <td style="color:#64748b"><?= number_format($r['upper'],2,'.',' ') ?></td>
          <td><span class="badge" style="background:#38bdf820;color:#7dd3fc;font-size:.68rem">Prévision</span></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- ══════════════════════════════════════════════════════
       BLOC 2 — Saisie manuelle + prédiction RF
  ══════════════════════════════════════════════════════ -->
  <div class="card">
    <h2><span class="dot" style="background:#4ade80"></span>Prédiction CA par produit (Random Forest)</h2>
    <form method="POST" action="">
      <div class="predict-form">
        <div class="fgroup">
          <label>ID Produit</label>
          <input type="number" name="id_produit" value="<?= $rf_input['id_produit'] ?>" min="1" required>
        </div>
        <div class="fgroup">
          <label>Mois</label>
          <input type="number" name="mois" value="<?= $rf_input['mois'] ?>" min="1" max="12" required>
        </div>
        <div class="fgroup">
          <label>Année</label>
          <input type="number" name="annee" value="<?= $rf_input['annee'] ?>" min="2020" max="2030" required>
        </div>
        <div class="fgroup">
          <label>Nb commandes</label>
          <input type="number" name="nb_commandes" value="<?= $rf_input['nb_commandes'] ?>" min="0" required>
        </div>
        <button class="btn" type="submit" name="predict">Prédire →</button>
      </div>
    </form>

    <?php if($submitted): ?>
      <?php if(!empty($rf_result['ok'])): ?>
      <div class="result-box">
        <div>
          <div class="big"><?= number_format($rf_result['ca'],2,'.',' ') ?> <span style="font-size:1rem;color:#64748b">DT</span></div>
          <div class="sub">CA prédit — Produit #<?= $rf_input['id_produit'] ?>, <?= $rf_input['mois'] ?>/<?= $rf_input['annee'] ?>, <?= $rf_input['nb_commandes'] ?> commandes</div>
        </div>
      </div>
      <?php else: ?>
        <div class="err">⚠ <?= htmlspecialchars($rf_result['error'] ?? 'Erreur inconnue') ?></div>
        <?php if(str_contains($rf_result['error']??'','xgboost')): ?>
          <p style="color:#64748b;font-size:.78rem;margin-top:8px">💡 Fix : <code>pip install xgboost</code></p>
        <?php endif; ?>
      <?php endif; ?>
    <?php else: ?>
      <p style="color:#475569;font-size:.8rem;margin-top:6px">Remplissez les champs et cliquez sur <strong>Prédire</strong>.</p>
    <?php endif; ?>
  </div>

  <!-- ══════════════════════════════════════════════════════
       BLOC 3 — Clusters
  ══════════════════════════════════════════════════════ -->
  <div class="card">
    <h2><span class="dot" style="background:#c084fc"></span>Clustering produits (KMeans)</h2>
    <?php if(!empty($cluster_data['error'])): ?>
      <div class="err">⚠ <?= htmlspecialchars($cluster_data['error']) ?></div>
    <?php else: ?>
    <div class="chart-wrap" style="height:180px"><canvas id="clusterChart"></canvas></div>
    <table class="tbl" style="margin-top:12px">
      <tr><th>Cluster</th><th>#Produits</th><th>CA moyen</th><th>Qté moy.</th></tr>
      <?php foreach($cluster_data['rows'] as $r): ?>
      <tr>
        <td><span class="badge b-purple"><?= htmlspecialchars($r['cluster']) ?></span></td>
        <td><?= (int)$r['nb_produits'] ?></td>
        <td><?= number_format($r['ca_moyen'],2,'.',' ') ?></td>
        <td><?= number_format($r['qte_moyenne'],1,'.',' ') ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>

  <!-- ══════════════════════════════════════════════════════
       BLOC 4 — Association rules
  ══════════════════════════════════════════════════════ -->
  <div class="card card-full">
    <h2><span class="dot" style="background:#fb923c"></span>Top 10 règles d'association (FP-Growth)</h2>
    <?php if(!empty($rules_data['error'])): ?>
      <div class="err">⚠ <?= htmlspecialchars($rules_data['error']) ?></div>
    <?php else: ?>
    <?php $max_lift = max(array_column($rules_data['rows'],'lift') ?: [1]); ?>
    <table class="tbl">
      <tr><th>Si le client achète…</th><th>…il achètera</th><th>Lift</th><th>Confiance</th></tr>
      <?php foreach($rules_data['rows'] as $r): ?>
      <tr>
        <td><code style="color:#fbbf24"><?= htmlspecialchars($r['antecedents']) ?></code></td>
        <td><code style="color:#34d399"><?= htmlspecialchars($r['consequents']) ?></code></td>
        <td>
          <span class="badge b-orange"><?= $r['lift'] ?></span>
          <span class="lift-bar" style="width:<?= round(($r['lift']/$max_lift)*80) ?>px"></span>
        </td>
        <td><?= round($r['confidence']*100,1) ?>%</td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>

  <!-- Power BI -->
  <div class="card card-full">
    <h2><span class="dot" style="background:#e879f9"></span>Power BI — Rapport</h2>
    <iframe width="100%" height="780"
      src="https://app.powerbi.com/reportEmbed?reportId=17bc6525-01d3-49e8-af6e-339df47b37c9&autoAuth=true&ctid=604f1a96-cbe8-43f8-abbf-f8eaf5d85730"
      frameborder="0" style="border-radius:8px;border:none"></iframe>
  </div>

</div><!-- /page -->

<script>
// ── Série temporelle : historique (bleu) + prévision (orange) ────────────────
(function(){
  const all    = <?= json_encode($prophet_data['rows'] ?? []) ?>;
  if(!all.length) return;

  const labels = all.map(r => r.ds);
  const future = all.map(r => r.is_future);

  // Split en deux séries séparées — overlap d'1 point à la jonction
  const splitIdx = future.indexOf(true);

  // Historique : points réels
  const histY = all.map((r,i) => i <= splitIdx ? r.yhat : null);
  // Prévision : points futurs (+ dernier point historique pour continuité)
  const futY  = all.map((r,i) => i >= (splitIdx > 0 ? splitIdx-1 : 0) && r.is_future || i === splitIdx-1 ? r.yhat : null);
  // Bandes confiance futures seulement
  const bandU = all.map(r => r.is_future ? r.upper : null);
  const bandL = all.map(r => r.is_future ? r.lower : null);

  new Chart(document.getElementById('prophetChart'), {
    type:'line',
    data:{
      labels,
      datasets:[
        // Bande supérieure (fill vers bandL = dataset suivant)
        { data: bandU, borderColor:'transparent', backgroundColor:'rgba(245,158,11,0.12)',
          pointRadius:0, fill:'+1', tension:.4, label:'Intervalle' },
        // Bande inférieure
        { data: bandL, borderColor:'transparent', backgroundColor:'transparent',
          pointRadius:0, fill:false, tension:.4, label:'' },
        // Historique
        { data: histY, label:'CA réel', borderColor:'#38bdf8',
          backgroundColor:'transparent', pointRadius:2, pointBackgroundColor:'#38bdf8',
          tension:.4, fill:false, borderWidth:2.5, spanGaps:false },
        // Prévision
        { data: futY, label:'Prévision', borderColor:'#f59e0b',
          backgroundColor:'transparent', pointRadius:4, pointBackgroundColor:'#f59e0b',
          tension:.4, fill:false, borderWidth:2.5, borderDash:[6,3], spanGaps:false },
      ]
    },
    options:{
      responsive:true, maintainAspectRatio:false,
      interaction:{mode:'index', intersect:false},
      plugins:{
        legend:{
          display:true,
          labels:{color:'#94a3b8', boxWidth:14, font:{size:11},
            filter: item => item.text !== ''}
        },
        tooltip:{
          backgroundColor:'#1e293b', borderColor:'#334155', borderWidth:1,
          titleColor:'#e2e8f0', bodyColor:'#94a3b8',
          callbacks:{
            label: ctx => {
              if(ctx.parsed.y === null) return null;
              const tag = ctx.dataset.label;
              const val = ctx.parsed.y.toLocaleString('fr-FR',{minimumFractionDigits:0});
              return ` ${tag}: ${val} DT`;
            }
          }
        }
      },
      scales:{
        x:{ ticks:{color:'#475569', maxTicksLimit:10, maxRotation:35},
            grid:{color:'#1e3a5f33'} },
        y:{ ticks:{color:'#475569',
              callback: v => v.toLocaleString('fr-FR')},
            grid:{color:'#1e3a5f33'} }
      }
    }
  });
})();

// ── Cluster bar chart ─────────────────────────────────────────────────────────
(function(){
  const el = document.getElementById('clusterChart');
  if(!el) return;
  <?php if(empty($cluster_data['error']) && !empty($cluster_data['rows'])): ?>
  const labels = <?= json_encode(array_column($cluster_data['rows'],'cluster')) ?>;
  const ca     = <?= json_encode(array_column($cluster_data['rows'],'ca_moyen')) ?>;
  new Chart(el, {
    type:'bar',
    data:{ labels, datasets:[{ label:'CA moyen', data:ca,
      backgroundColor:['#38bdf880','#c084fc80','#4ade8080','#fb923c80'],
      borderRadius:6 }] },
    options:{responsive:true,maintainAspectRatio:false,
      plugins:{legend:{display:false}},
      scales:{x:{ticks:{color:'#475569'},grid:{display:false}},
              y:{ticks:{color:'#475569'},grid:{color:'#1e3a5f44'}}}}
  });
  <?php endif; ?>
})();
</script>
</body>
</html>