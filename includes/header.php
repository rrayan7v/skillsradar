<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Basis URL voor alle links en assets
$base_url = '/Skillradar'; // pas dit aan als de site ergens anders staat
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skillradar</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= $base_url ?>/assets/img/favicon.png">

    <!-- CSS -->
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/style.css">
</head>
<body>

<header>
    <nav class="navbar">
        <div class="logo">
            <a href="<?= $base_url ?>/index.php">
                <img src="<?= $base_url ?>/assets/img/gildeopleidingen.png" alt="Gilde Opleidingen Logo">
            </a>
        </div>
        <ul class="nav-links">
            <li><a href="<?= $base_url ?>/index.php">Home</a></li>
            
            <?php if(isset($_SESSION['user_id'])): ?>
                <li><a href="<?= $base_url ?>/dashboard/dashboard.php">Dashboard</a></li>
                <li><a href="<?= $base_url ?>/login/logout.php" class="btn-logout">Logout</a></li>
            <?php else: ?>
                <li><a href="<?= $base_url ?>/login/login.php" class="btn-login">Login</a></li>
                <li><a href="<?= $base_url ?>/login/register.php" class="btn-register">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>
