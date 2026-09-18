<?php
include("config.php");

$message = "";

if(isset($_POST['login']))
{
    $email = $_POST['email'];
    $password = md5($_POST['password']);

    $sql = "
    SELECT *
    FROM users
    WHERE email='$email'
    AND password='$password'
    AND actif=1
    ";

    $result = $conn->query($sql);

    if($result->num_rows > 0)
    {
        $user = $result->fetch_assoc();

        $_SESSION['id'] = $user['id'];
        $_SESSION['nom'] = $user['nom'];
        $_SESSION['role'] = $user['role'];

        if($user['role'] == "admin")
            header("Location: admin.php");
        elseif($user['role'] == "commercial")
            header("Location: dashboard_commercial.php");
        elseif($user['role'] == "employe")
            header("Location: dashboard_employe.php");

        exit;
    }
    else
    {
        $message = "Email ou mot de passe incorrect";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Connexion - Dashboard ML</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{
            font-family:Inter,Segoe UI,sans-serif;
            background:#0f172a;
            color:#e2e8f0;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
        }
        .card{
            background:#1e293b;
            border-radius:12px;
            padding:40px 36px;
            width:100%;
            max-width:420px;
            border:1px solid #334155;
            box-shadow:0 10px 30px rgba(0,0,0,0.3);
        }
        h1{
            text-align:center;
            font-size:1.6rem;
            margin-bottom:8px;
            color:#38bdf8;
        }
        .subtitle{
            text-align:center;
            color:#64748b;
            margin-bottom:32px;
            font-size:0.95rem;
        }
        .form-group{
            margin-bottom:20px;
        }
        label{
            display:block;
            font-size:0.75rem;
            color:#64748b;
            text-transform:uppercase;
            letter-spacing:0.06em;
            margin-bottom:6px;
        }
        input{
            width:100%;
            background:#0f172a;
            border:1px solid #334155;
            color:#e2e8f0;
            padding:12px 16px;
            border-radius:8px;
            font-size:1rem;
        }
        input:focus{
            outline:none;
            border-color:#38bdf8;
        }
        .btn{
            width:100%;
            background:#0ea5e9;
            color:#fff;
            border:none;
            padding:14px;
            border-radius:8px;
            font-size:1rem;
            font-weight:600;
            cursor:pointer;
            margin-top:10px;
        }
        .btn:hover{
            background:#0284c7;
        }
        .err{
            color:#f87171;
            background:#7f1d1d33;
            border:1px solid #f8717133;
            padding:12px;
            border-radius:8px;
            margin-top:16px;
            text-align:center;
        }
    </style>
</head>
<body>

<div class="card">
    <h1>Analyse des données</h1>
    <p class="subtitle">Connectez-vous pour accéder à l'analytique</p>

    <?php if(!empty($message)): ?>
        <div class="err">⚠ <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required>
        </div>

        <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" name="password" required>
        </div>

        <button type="submit" name="login" class="btn">Se connecter</button>
    </form>
</div>

</body>
</html>