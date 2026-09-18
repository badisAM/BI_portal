<?php
include("config.php");
if(!isset($_SESSION['role']) || $_SESSION['role']!="admin"){
    header("Location: login.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard Administration</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,Segoe UI,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh}
header{background:#1e293b;padding:14px 28px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #334155;position:sticky;top:0;z-index:10}
header h1{font-size:1.1rem;font-weight:700;color:#38bdf8}
header nav a{color:#94a3b8;text-decoration:none;margin-left:18px;font-size:.83rem}
header nav a:hover{color:#e2e8f0}
.page{padding:22px 24px;display:grid;gap:20px}
.card{background:#1e293b;border-radius:12px;padding:20px;border:1px solid #334155}
.card-full{grid-column:1/-1}
.card h2{font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.dot{width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0}
.nav-links{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:4px}
.nav-card{background:#0f172a;border:1px solid #334155;border-radius:10px;padding:14px 20px;display:flex;align-items:center;gap:12px;text-decoration:none;color:#e2e8f0;font-size:.88rem;font-weight:500;transition:border-color .2s,background .2s}
.nav-card:hover{border-color:#38bdf8;background:#1e3a5f55;color:#38bdf8}
.nav-card .icon{font-size:1.3rem}
</style>
</head>
<body>
<header>
  <h1>🛠 Dashboard Administration</h1>
  <nav>
    <a href="config_employes.php">Utilisateurs</a>
    <a href="dashboard_ml.php">📊 Dashboard ML</a>
    <a href="logout.php">Déconnexion</a>
  </nav>
</header>

<div class="page">

  <div class="card card-full">
    <h2><span class="dot" style="background:#38bdf8"></span>Navigation principale</h2>
    <div class="nav-links">
      <a href="config_employes.php" class="nav-card">
        <span class="icon">👥</span> Gestion Utilisateurs
      </a>
      <a href="dashboard_ml.php" class="nav-card">
        <span class="icon">📊</span> Dashboard ML
      </a>
      <a href="logout.php" class="nav-card">
        <span class="icon">🚪</span> Déconnexion
      </a>
    </div>
  </div>

  <div class="card card-full">
    <h2><span class="dot" style="background:#e879f9"></span>Power BI — Rapport</h2>
    <iframe
      width="100%"
      height="900"
      src="https://app.powerbi.com/reportEmbed?reportId=17bc6525-01d3-49e8-af6e-339df47b37c9&autoAuth=true&ctid=604f1a96-cbe8-43f8-abbf-f8eaf5d85730"
      frameborder="0"
      style="border-radius:8px;border:none">
    </iframe>
  </div>

</div><!-- /page -->
</body>
</html>