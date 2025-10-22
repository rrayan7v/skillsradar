<?php
session_start();
include '../includes/db.php'; // Database connectie

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE email='$email' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role']; // ✅ Gebruikersrol opslaan

            header("Location: ../dashboard/dashboard.php");
            exit();
        } else {
            $error = "Ongeldig wachtwoord!";
        }
    } else {
        $error = "Geen account gevonden met dit e-mailadres!";
    }
}
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inloggen - Gilde Skillsradar</title>
    <link rel="stylesheet" href=".assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
</head>

<body class="login-page">
    <!-- Login Hero Section -->
    <section class="login-hero">
        <div class="login-container">
            <div class="login-logo">
                <img src="../assets/img/gildeopleidingen.png" alt="Gilde Skillsradar">
            </div>

            <h2>Welkom terug!</h2>
            <p>Log in om je vragenlijsten en resultaten te bekijken.</p>

            <?php if ($error != ''): ?>
                <div class="error-msg"><?php echo $error; ?></div>
            <?php endif; ?>

            <form action="" method="POST" class="login-form">
                <input type="email" name="email" placeholder="E-mailadres" required>
                <input type="password" name="password" placeholder="Wachtwoord" required>
                <button type="submit" class="btn-primary">Inloggen</button>
            </form>

            <p class="login-register">
                Nog geen account? <a href="register.php">Registreer hier</a>
            </p>
        </div>

        <!-- Floating Shapes -->
        <div class="floating-shape shape1"></div>
        <div class="floating-shape shape2"></div>
        <div class="floating-shape shape3"></div>
    </section>
</body>
</html>
