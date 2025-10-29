<?php
session_start();
include '../includes/db.php'; // database connectie

$error = '';
$nameInput = '';
$emailInput = '';

// Hulpfunctie om e-maildomeinen veilig te controleren
function emailEndsWith(string $email, string $domain): bool {
    $domainLength = strlen($domain);
    if ($domainLength === 0) {
        return false;
    }

    return strlen($email) >= $domainLength && substr($email, -$domainLength) === $domain;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nameInput = trim($_POST['name'] ?? '');
    $emailInput = trim($_POST['email'] ?? '');
    $passwordInput = $_POST['password'] ?? '';

    $emailLower = strtolower($emailInput);
    $role = '';

    // Bepaal de rol op basis van het e-mailadresdomein
    if (emailEndsWith($emailLower, '@student.gildeopleidingen.nl')) {
        $role = 'student';
    } elseif (emailEndsWith($emailLower, '@rocgilde.nl')) {
        $role = 'teacher';
    } else {
        $error = "Gebruik je school e-mailadres (@student.gildeopleidingen.nl of @rocgilde.nl) om je te registreren.";
    }

    if ($error === '') {
        $name = mysqli_real_escape_string($conn, $nameInput);
        $email = mysqli_real_escape_string($conn, $emailLower);
        $password = password_hash($passwordInput, PASSWORD_BCRYPT);

        // Controleer of e-mail al bestaat
        $check = mysqli_query($conn, "SELECT id FROM users WHERE email='$email'");
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
            <input type="text" name="name" placeholder="Naam" value="<?php echo htmlspecialchars($nameInput ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            <input type="email" name="email" placeholder="E-mailadres" value="<?php echo htmlspecialchars($emailInput ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            <input type="password" name="password" placeholder="Wachtwoord" required>
            <button type="submit" class="btn-primary">Account aanmaken</button>
        </form>

        <p class="register-note">Gebruik je school e-mailadres eindigend op @student.gildeopleidingen.nl of @rocgilde.nl.</p>

        <p class="register-login">Heb je al een account? <a href="login.php">Inloggen</a></p>
    </div>

    <!-- Floating shapes -->
    <div class="floating-shape shape1"></div>
    <div class="floating-shape shape2"></div>
    <div class="floating-shape shape3"></div>
</section>

</body>
</html>
