<?php
session_start();
include '../includes/header.php';

// Als niet ingelogd -> terug naar login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit();
}

$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
// Normalize role values (handle Dutch/English labels)
$roleNorm = strtolower(trim($userRole));
$isTeacher = in_array($roleNorm, ['docent', 'teacher']);
$isStudent = in_array($roleNorm, ['student', 'leerling']);
// fallback: if role empty or unknown treat as student for safety (read-only)
if (!$isTeacher && !$isStudent) {
    $isStudent = true;
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gilde Skillsradar</title>
    <link rel="stylesheet" href="/Skillradar/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="/Skillradar/assets/img/favicon.png">
</head>

<body class="dashboard-page">
    <section class="dashboard-hero">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1>Welkom terug, <?php echo htmlspecialchars($userName); ?></h1>
                <p><?php echo $isTeacher ? 'Beheer jouw vragenlijsten, groepen en resultaten.' : 'Bekijk openstaande vragenlijsten en jouw persoonlijke resultaten.'; ?></p>
            </div>

            <div class="dashboard-grid">
                <?php if ($isTeacher): ?>
                    <!-- TEACHER / DOCENT -->
                    <div class="dashboard-card">
                        <div class="icon-circle">
                            <img src="https://cdn-icons-png.flaticon.com/512/2910/2910763.png" alt="Nieuwe vragenlijst" />
                        </div>
                        <h3>Nieuwe vragenlijst</h3>
                        <p>Maak, bewerk en verstuur vragenlijsten naar studenten.</p>
                        <a href="/Skillradar/survey/survey.php" class="btn-primary">Maak vragenlijst</a>
                    </div>

                    <div class="dashboard-card">
                        <div class="icon-circle">
                            <img src="https://cdn-icons-png.flaticon.com/512/2910/2910769.png" alt="Groepen aanmaken" />
                        </div>
                        <h3>Groepen aanmaken</h3>
                        <p>Maak nieuwe groepen aan en beheer je teams.</p>
                        <a href="/Skillradar/survey/groups.php" class="btn-primary">Maak groep</a>
                    </div>

                    <div class="dashboard-card">
                        <div class="icon-circle">
                            <img src="https://cdn-icons-png.flaticon.com/512/2910/2910763.png" alt="Bekijk alle vragenlijsten" />
                        </div>
                        <h3>Bekijk alle vragenlijsten</h3>
                        <p>Bekijk en beheer alle vragenlijsten die je hebt aangemaakt.</p>
                        <a href="/Skillradar/survey/view_surveys.php" class="btn-primary">Bekijk vragenlijsten</a>
                    </div>

                    <div class="dashboard-card">
                        <div class="icon-circle">
                            <img src="https://cdn-icons-png.flaticon.com/512/2910/2910798.png" alt="Bekijk resultaten" />
                        </div>
                        <h3>Bekijk resultaten</h3>
                        <p>Analyseer groepsresultaten en exporteer rapporten.</p>
                        <a href="/Skillradar/survey/view_survey.php" class="btn-primary">Bekijk resultaten</a>
                    </div>

                <?php else: ?>
                    <!-- STUDENT / LEERLING -->
                    <div class="dashboard-card">
                        <div class="icon-circle">
                            <img src="https://cdn-icons-png.flaticon.com/512/2910/2910763.png" alt="Openstaande vragenlijsten" />
                        </div>
                        <h3>Openstaande vragenlijsten</h3>
                        <p>Bekijk en vul de vragenlijsten die voor jou klaarstaan.</p>
                        <a href="/Skillradar/survey/take_survey.php" class="btn-primary">Invullen</a>
                    </div>

                    <div class="dashboard-card">
                        <div class="icon-circle">
                            <img src="https://cdn-icons-png.flaticon.com/512/2910/2910798.png" alt="Mijn resultaten" />
                        </div>
                        <h3>Mijn resultaten</h3>
                        <p>Bekijk je persoonlijke scores en voortgang per skill.</p>
                        <a href="/Skillradar/survey/view_survey.php" class="btn-primary">Bekijk resultaten</a>
                    </div>

                    <div class="dashboard-card">
                        <div class="icon-circle">
                            <img src="https://cdn-icons-png.flaticon.com/512/2910/2910741.png" alt="Mijn groep" />
                        </div>
                        <h3>Mijn groep</h3>
                        <p>Bekijk je groepsleden en gezamenlijke resultaten.</p>
                        <a href="/Skillradar/survey/student_groups.php" class="btn-primary">Bekijk groep</a>
                    </div>

                <?php endif; ?>
            </div>
        </div>

        <!-- Achtergrondvormen -->
        <div class="floating-shape shape1"></div>
        <div class="floating-shape shape2"></div>
        <div class="floating-shape shape3"></div>
    </section>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
