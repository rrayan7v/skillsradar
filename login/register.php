<?php
session_start();
include '../includes/db.php'; // database connectie

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = mysqli_real_escape_string($conn, $_POST['role']);

    // Controleer of e-mail al bestaat
    $check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    if (mysqli_num_rows($check) > 0) {
        $error = "Er bestaat al een account met dit e-mailadres!";
    } else {
        $query = "INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$password', '$role')";
        if (mysqli_query($conn, $query)) {
            header("Location: login.php");
            exit();
        } else {
            $error = "Er ging iets mis bij het registreren. Probeer opnieuw.";
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registreren - Gilde Skillsradar</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
</head>

<body class="register-page">

<section class="register-hero">
    <div class="register-container">
        <div class="register-logo">
            <img src="../assets/img/gildeopleidingen.png" alt="Gilde Skillsradar">
        </div>

        <h2>Account aanmaken</h2>
        <p>Meld je aan om toegang te krijgen tot jouw Skillradar omgeving.</p>

        <?php if ($error != ''): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="text" name="name" placeholder="Naam" required>
            <input type="email" name="email" placeholder="E-mailadres" required>
            <input type="password" name="password" placeholder="Wachtwoord" required>
            <select name="role" required>
                <option value="">Selecteer rol</option>
                <option value="student">Student</option>
                <option value="teacher">Docent</option>
            </select>
            <button type="submit" class="btn-primary">Account aanmaken</button>
        </form>

        <p class="register-login">Heb je al een account? <a href="login.php">Inloggen</a></p>
    </div>

    <!-- Floating shapes -->
    <div class="floating-shape shape1"></div>
    <div class="floating-shape shape2"></div>
    <div class="floating-shape shape3"></div>
</section>

</body>
</html>
