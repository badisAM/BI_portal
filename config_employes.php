<?php
include("config.php");
if(!isset($_SESSION['role']) || $_SESSION['role']!="admin"){
    header("Location: login.php"); exit;
}

if(isset($_GET['toggle'])){
    $id=(int)$_GET['toggle'];
    $conn->query("UPDATE users SET actif = 1-actif WHERE id=$id");
}

$result=$conn->query("SELECT * FROM users WHERE role <> 'admin' ORDER BY role,nom");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gestion Utilisateurs</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,Segoe UI,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh}
header{background:#1e293b;padding:14px 28px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #334155;position:sticky;top:0;z-index:10}
header h1{font-size:1.1rem;font-weight:700;color:#38bdf8}
header nav a{color:#94a3b8;text-decoration:none;margin-left:18px;font-size:.83rem}
header nav a:hover{color:#e2e8f0}
.page{padding:22px 24px;display:grid;gap:20px}
.card{background:#1e293b;border-radius:12px;padding:20px;border:1px solid #334155}
.card h2{font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.dot{width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0}
.tbl{width:100%;border-collapse:collapse;font-size:.82rem}
.tbl th{color:#64748b;font-weight:600;text-align:left;padding:5px 8px;border-bottom:1px solid #334155}
.tbl td{padding:6px 8px;border-bottom:1px solid #ffffff08}
.tbl tr:last-child td{border:none}
.tbl tr:hover td{background:#ffffff06}
.badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.73rem;font-weight:600}
.b-green{background:#22c55e20;color:#4ade80}
.b-red{background:#ef444420;color:#f87171}
.b-blue{background:#0ea5e920;color:#38bdf8}
.b-purple{background:#a855f720;color:#c084fc}
.btn-toggle{display:inline-block;padding:4px 12px;border-radius:6px;font-size:.75rem;font-weight:600;text-decoration:none;transition:opacity .2s}
.btn-toggle:hover{opacity:.8}
.btn-desact{background:#ef444420;color:#f87171;border:1px solid #ef444430}
.btn-act{background:#22c55e20;color:#4ade80;border:1px solid #22c55e30}
</style>
</head>
<body>
<header>
  <h1>👥 Gestion Utilisateurs</h1>
  <nav>
    <a href="admin.php">← Admin</a>
    <a href="dashboard_ml.php">📊 Dashboard ML</a>
    <a href="logout.php">Déconnexion</a>
  </nav>
</header>

<div class="page">
  <div class="card">
    <h2><span class="dot" style="background:#38bdf8"></span>Liste des utilisateurs</h2>
    <table class="tbl">
      <tr>
        <th>Nom</th>
        <th>Email</th>
        <th>Rôle</th>
        <th>État</th>
        <th>Action</th>
      </tr>
      <?php while($u=$result->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($u['nom']) ?></td>
        <td style="color:#94a3b8"><?= htmlspecialchars($u['email']) ?></td>
        <td>
          <?php if($u['role']==='commercial'): ?>
            <span class="badge b-blue">Commercial</span>
          <?php elseif($u['role']==='employe'): ?>
            <span class="badge b-purple">Employé</span>
          <?php else: ?>
            <span class="badge b-blue"><?= htmlspecialchars($u['role']) ?></span>
          <?php endif; ?>
        </td>
        <td>
          <?php if($u['actif']): ?>
            <span class="badge b-green">Actif</span>
          <?php else: ?>
            <span class="badge b-red">Inactif</span>
          <?php endif; ?>
        </td>
        <td>
          <a href="?toggle=<?= $u['id'] ?>" class="btn-toggle <?= $u['actif'] ? 'btn-desact' : 'btn-act' ?>">
            <?= $u['actif'] ? 'Désactiver' : 'Activer' ?>
          </a>
        </td>
      </tr>
      <?php endwhile; ?>
    </table>
  </div>
</div>
</body>
</html>